<#
  Builds the N Events brand assets from the official master logo.

    powershell -ExecutionPolicy Bypass -File bin\build-brand-assets.ps1 [-Source path\to\logo.(webp|png)]

  Default source: resources\brand\n-events-logo-master.webp (the official file as
  supplied — kept outside public/ because only this script needs it)

  The logo is one brand color on a flat cream background. This script only
  crops, removes that flat background (alpha = how far each pixel is from the
  cream, so anti-aliased edges are preserved and the brand color itself is
  never changed) and downsamples with area averaging. It never redraws the
  artwork. -Ink / -Background (hex) repaint the output in brand colors: the
  shape (alpha mask) still comes from the source file, only the fill changes.
    ... -Ink 4B2E1E -Background F6EFE4   (current brand: Brown on Cream) Uses Windows' built-in WIC codecs (WebP supported on
  Windows 10 1809+/11) — no GD/ImageMagick needed.

  Outputs (public\images\brand\):
    n-events-logo.png       full logo (mark + EVENTS), transparent, 512px tall
    n-events-mark.png       N + pin symbol only, transparent, 256px tall (navbar)
    favicon-32.png          mark, transparent, 32x32
    favicon-192.png         mark on cream, 192x192 (PWA / Android)
    apple-touch-icon.png    mark on cream, 180x180 (iOS ignores transparency)
    icon-512.png            mark on cream, 512x512 (web-app manifest)
    icon-maskable-512.png   mark on cream inside the maskable safe zone, 512x512

  -AppAssets also writes the Android / iOS app sources (mobile\assets\, read by
  `npm run assets` in mobile\):
    icon-only.png           mark on cream, 1024x1024 (iOS icon, Android legacy icon)
    icon-foreground.png     mark, transparent, for Android's adaptive icon, 1024x1024
    icon-background.png     flat cream, 1024x1024 (adaptive icon background)
    splash.png              mark on cream, 2732x2732 (launch screen)
#>
param([string]$Source = (Join-Path $PSScriptRoot '..\resources\brand\n-events-logo-master.webp'),
      [string]$Ink = '', [string]$Background = '', [switch]$AppAssets)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName PresentationCore, WindowsBase

Add-Type -ReferencedAssemblies PresentationCore, WindowsBase, System.Xaml -TypeDefinition @'
using System;
using System.IO;
using System.Windows.Media;
using System.Windows.Media.Imaging;

public static class BrandAssets
{
    public static int W, H; public static byte[] Px;          // BGRA source
    public static byte[] Bg = new byte[3];                       // background B,G,R
    public static byte[] Ink = new byte[3];                      // brand color B,G,R
    public static byte[] OutInk, OutBg;                          // output colors (default: source colors)

    public static void Load(string path)
    {
        var dec = BitmapDecoder.Create(new Uri(Path.GetFullPath(path)), BitmapCreateOptions.None, BitmapCacheOption.OnLoad);
        var f = new FormatConvertedBitmap(dec.Frames[0], PixelFormats.Bgra32, null, 0);
        W = f.PixelWidth; H = f.PixelHeight; Px = new byte[W * H * 4];
        f.CopyPixels(Px, W * 4, 0);
        // background / ink = the MOST FREQUENT light / dark color (not the extreme
        // pixel, which in a compressed file is an artifact)
        var light = new System.Collections.Generic.Dictionary<int, int>();
        var dark  = new System.Collections.Generic.Dictionary<int, int>();
        for (int i = 0; i < Px.Length; i += 16)
        {
            int key = (Px[i + 2] << 16) | (Px[i + 1] << 8) | Px[i];
            int sum = Px[i] + Px[i + 1] + Px[i + 2];
            var d = sum > 600 ? light : (sum < 300 ? dark : null);
            if (d != null) { int c; d.TryGetValue(key, out c); d[key] = c + 1; }
        }
        int bgKey = 0, inkKey = 0, bgN = 0, inkN = 0;
        foreach (var kv in light) if (kv.Value > bgN) { bgN = kv.Value; bgKey = kv.Key; }
        foreach (var kv in dark)  if (kv.Value > inkN) { inkN = kv.Value; inkKey = kv.Key; }
        Bg[0] = (byte)bgKey; Bg[1] = (byte)(bgKey >> 8); Bg[2] = (byte)(bgKey >> 16);
        Ink[0] = (byte)inkKey; Ink[1] = (byte)(inkKey >> 8); Ink[2] = (byte)(inkKey >> 16);
    }

    static double Alpha(int i)
    {
        double lb = Bg[0] + Bg[1] + Bg[2], li = Ink[0] + Ink[1] + Ink[2], lp = Px[i] + Px[i + 1] + Px[i + 2];
        double a = (lb - lp) / (lb - li);
        return a < 0.03 ? 0 : (a > 0.97 ? 1 : a);
    }

    /// bounding box of logo pixels inside [y0,y1)
    public static int[] BBox(int y0, int y1)
    {
        int minX = W, minY = H, maxX = 0, maxY = 0;
        for (int y = y0; y < y1; y++) for (int x = 0; x < W; x++)
            if (Alpha((y * W + x) * 4) > 0.2) { if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
        return new[] { minX, minY, maxX - minX + 1, maxY - minY + 1 };
    }

    /// Crops [cx,cy,cw,ch], fits it into outW x outH with `pad` px margin, optional flat background
    public static void Save(string file, int cx, int cy, int cw, int ch, int outW, int outH, int pad, bool onBackground)
    {
        double scale = Math.Min((outW - 2.0 * pad) / cw, (outH - 2.0 * pad) / ch);
        int dw = (int)Math.Round(cw * scale), dh = (int)Math.Round(ch * scale);
        int ox = (outW - dw) / 2, oy = (outH - dh) / 2;
        var outPx = new byte[outW * outH * 4];
        for (int y = 0; y < outH; y++) for (int x = 0; x < outW; x++)
        {
            int o = (y * outW + x) * 4; double a = 0;
            if (x >= ox && x < ox + dw && y >= oy && y < oy + dh)
            {
                // area-average every source pixel covered by this output pixel
                double sx0 = cx + (x - ox) / scale, sx1 = cx + (x - ox + 1) / scale;
                double sy0 = cy + (y - oy) / scale, sy1 = cy + (y - oy + 1) / scale;
                double sum = 0, n = 0;
                for (int sy = (int)sy0; sy < Math.Ceiling(sy1) && sy < H; sy++)
                    for (int sx = (int)sx0; sx < Math.Ceiling(sx1) && sx < W; sx++) { sum += Alpha((sy * W + sx) * 4); n++; }
                a = n > 0 ? sum / n : 0;
            }
            if (onBackground)
            {
                for (int c = 0; c < 3; c++) outPx[o + c] = (byte)Math.Round(OutInk[c] * a + OutBg[c] * (1 - a));
                outPx[o + 3] = 255;
            }
            else
            {
                outPx[o] = OutInk[0]; outPx[o + 1] = OutInk[1]; outPx[o + 2] = OutInk[2];
                outPx[o + 3] = (byte)Math.Round(a * 255);
            }
        }
        var bmp = BitmapSource.Create(outW, outH, 96, 96, PixelFormats.Bgra32, null, outPx, outW * 4);
        var enc = new PngBitmapEncoder(); enc.Frames.Add(BitmapFrame.Create(bmp));
        using (var fs = File.Create(file)) enc.Save(fs);
    }

    /// A flat square in the output background color
    public static void SaveSolid(string file, int size)
    {
        var outPx = new byte[size * size * 4];
        for (int o = 0; o < outPx.Length; o += 4) { outPx[o] = OutBg[0]; outPx[o + 1] = OutBg[1]; outPx[o + 2] = OutBg[2]; outPx[o + 3] = 255; }
        var bmp = BitmapSource.Create(size, size, 96, 96, PixelFormats.Bgra32, null, outPx, size * 4);
        var enc = new PngBitmapEncoder(); enc.Frames.Add(BitmapFrame.Create(bmp));
        using (var fs = File.Create(file)) enc.Save(fs);
    }
}
'@

$outDir = Join-Path $PSScriptRoot '..\public\images\brand'
[BrandAssets]::Load($Source)
function ConvertTo-Bgr([string]$hex, [byte[]]$fallback) {
    if ($hex -eq '') { return $fallback }
    $h = $hex.TrimStart('#')
    return [byte[]]@([Convert]::ToByte($h.Substring(4, 2), 16), [Convert]::ToByte($h.Substring(2, 2), 16), [Convert]::ToByte($h.Substring(0, 2), 16))
}
[BrandAssets]::OutInk = ConvertTo-Bgr $Ink ([BrandAssets]::Ink)
[BrandAssets]::OutBg  = ConvertTo-Bgr $Background ([BrandAssets]::Bg)
"source {0}x{1}  background #{2:X2}{3:X2}{4:X2}  brand #{5:X2}{6:X2}{7:X2}" -f [BrandAssets]::W, [BrandAssets]::H,
    [BrandAssets]::Bg[2], [BrandAssets]::Bg[1], [BrandAssets]::Bg[0], [BrandAssets]::Ink[2], [BrandAssets]::Ink[1], [BrandAssets]::Ink[0]

$full = [BrandAssets]::BBox(0, [BrandAssets]::H)
# The mark (N + pin) ends where the gap above the EVENTS wordmark begins: find the first
# empty band of rows scanning upward from the bottom of the full logo.
$markBottom = $full[1] + $full[3]
$rowsSeenEmpty = 0
for ($y = $full[1] + $full[3] - 1; $y -gt $full[1]; $y--) {
    $row = [BrandAssets]::BBox($y, $y + 1)
    if ($row[2] -le 0) { $rowsSeenEmpty++ } elseif ($rowsSeenEmpty -ge 20) { $markBottom = $y + 1; break } else { $rowsSeenEmpty = 0 }
}
$mark = [BrandAssets]::BBox($full[1], $markBottom)
"full logo bbox: $($full -join ', ')   mark bbox: $($mark -join ', ')"

$fw = [int][Math]::Round(512 * $full[2] / $full[3])
[BrandAssets]::Save((Join-Path $outDir 'n-events-logo.png'),    $full[0], $full[1], $full[2], $full[3], $fw, 512, 0, $false)
$mw = [int][Math]::Round(256 * $mark[2] / $mark[3])
[BrandAssets]::Save((Join-Path $outDir 'n-events-mark.png'),    $mark[0], $mark[1], $mark[2], $mark[3], $mw, 256, 0, $false)
[BrandAssets]::Save((Join-Path $outDir 'favicon-32.png'),       $mark[0], $mark[1], $mark[2], $mark[3], 32, 32, 1, $false)
[BrandAssets]::Save((Join-Path $outDir 'favicon-192.png'),      $mark[0], $mark[1], $mark[2], $mark[3], 192, 192, 24, $true)
[BrandAssets]::Save((Join-Path $outDir 'apple-touch-icon.png'), $mark[0], $mark[1], $mark[2], $mark[3], 180, 180, 22, $true)
# Web-app manifest icons: 512 standard, and 'maskable' with the mark inside Android's 80% safe zone
[BrandAssets]::Save((Join-Path $outDir 'icon-512.png'),         $mark[0], $mark[1], $mark[2], $mark[3], 512, 512, 64, $true)
[BrandAssets]::Save((Join-Path $outDir 'icon-maskable-512.png'), $mark[0], $mark[1], $mark[2], $mark[3], 512, 512, 112, $true)
Get-ChildItem $outDir -File | ForEach-Object { "{0,-24} {1,7:N0} bytes" -f $_.Name, $_.Length }

if ($AppAssets) {
    $appDir = Join-Path $PSScriptRoot '..\mobile\assets'
    New-Item -ItemType Directory -Force $appDir | Out-Null
    [BrandAssets]::Save((Join-Path $appDir 'icon-only.png'),       $mark[0], $mark[1], $mark[2], $mark[3], 1024, 1024, 150, $true)
    # capacitor-assets insets the adaptive-icon layers by 16.7%, which leaves exactly the circle
    # a launcher shows; a small margin keeps the mark clear of that circle's edge
    [BrandAssets]::Save((Join-Path $appDir 'icon-foreground.png'), $mark[0], $mark[1], $mark[2], $mark[3], 1024, 1024, 170, $false)
    [BrandAssets]::SaveSolid((Join-Path $appDir 'icon-background.png'), 1024)
    [BrandAssets]::Save((Join-Path $appDir 'splash.png'),          $mark[0], $mark[1], $mark[2], $mark[3], 2732, 2732, 1110, $true)
    Get-ChildItem $appDir -File | ForEach-Object { "{0,-24} {1,9:N0} bytes" -f $_.Name, $_.Length }
}
