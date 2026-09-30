-- 010: All-India locations (2026-09-25).
-- Adds every state and union territory with its districts, the main city of each
-- district plus other major places, common alternate names, and Puducherry town's
-- neighbourhoods. Tamil Nadu (state 1, districts 1-38) is left exactly as it was.
-- District names used in more than one state get the state in their slug
-- (bilaspur-chhattisgarh, bilaspur-himachal-pradesh). Idempotent: every insert is
-- keyed on a unique slug, so re-running adds only what is missing.

SET NAMES utf8mb4;

-- States and union territories
INSERT IGNORE INTO states (country_id, name, slug, is_active) VALUES
(1, 'Andhra Pradesh', 'andhra-pradesh', 1),
(1, 'Arunachal Pradesh', 'arunachal-pradesh', 1),
(1, 'Assam', 'assam', 1),
(1, 'Bihar', 'bihar', 1),
(1, 'Chhattisgarh', 'chhattisgarh', 1),
(1, 'Goa', 'goa', 1),
(1, 'Gujarat', 'gujarat', 1),
(1, 'Haryana', 'haryana', 1),
(1, 'Himachal Pradesh', 'himachal-pradesh', 1),
(1, 'Jharkhand', 'jharkhand', 1),
(1, 'Karnataka', 'karnataka', 1),
(1, 'Kerala', 'kerala', 1),
(1, 'Madhya Pradesh', 'madhya-pradesh', 1),
(1, 'Maharashtra', 'maharashtra', 1),
(1, 'Manipur', 'manipur', 1),
(1, 'Meghalaya', 'meghalaya', 1),
(1, 'Mizoram', 'mizoram', 1),
(1, 'Nagaland', 'nagaland', 1),
(1, 'Odisha', 'odisha', 1),
(1, 'Punjab', 'punjab', 1),
(1, 'Rajasthan', 'rajasthan', 1),
(1, 'Sikkim', 'sikkim', 1),
(1, 'Telangana', 'telangana', 1),
(1, 'Tripura', 'tripura', 1),
(1, 'Uttar Pradesh', 'uttar-pradesh', 1),
(1, 'Uttarakhand', 'uttarakhand', 1),
(1, 'West Bengal', 'west-bengal', 1),
(1, 'Andaman and Nicobar Islands', 'andaman-and-nicobar-islands', 1),
(1, 'Chandigarh', 'chandigarh', 1),
(1, 'Dadra and Nagar Haveli and Daman and Diu', 'dadra-and-nagar-haveli-and-daman-and-diu', 1),
(1, 'Delhi', 'delhi', 1),
(1, 'Jammu and Kashmir', 'jammu-and-kashmir', 1),
(1, 'Ladakh', 'ladakh', 1),
(1, 'Lakshadweep', 'lakshadweep', 1),
(1, 'Puducherry', 'puducherry', 1);

-- Andhra Pradesh: 26 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Alluri Sitharama Raju' AS name, 'alluri-sitharama-raju' AS slug
  UNION ALL SELECT 'Anakapalli', 'anakapalli'
  UNION ALL SELECT 'Anantapur', 'anantapur'
  UNION ALL SELECT 'Annamayya', 'annamayya'
  UNION ALL SELECT 'Bapatla', 'bapatla'
  UNION ALL SELECT 'Chittoor', 'chittoor'
  UNION ALL SELECT 'Dr. B.R. Ambedkar Konaseema', 'dr-br-ambedkar-konaseema'
  UNION ALL SELECT 'East Godavari', 'east-godavari'
  UNION ALL SELECT 'Eluru', 'eluru'
  UNION ALL SELECT 'Guntur', 'guntur'
  UNION ALL SELECT 'Kakinada', 'kakinada'
  UNION ALL SELECT 'Krishna', 'krishna'
  UNION ALL SELECT 'Kurnool', 'kurnool'
  UNION ALL SELECT 'Nandyal', 'nandyal'
  UNION ALL SELECT 'NTR', 'ntr'
  UNION ALL SELECT 'Palnadu', 'palnadu'
  UNION ALL SELECT 'Parvathipuram Manyam', 'parvathipuram-manyam'
  UNION ALL SELECT 'Prakasam', 'prakasam'
  UNION ALL SELECT 'Sri Potti Sriramulu Nellore', 'sri-potti-sriramulu-nellore'
  UNION ALL SELECT 'Sri Sathya Sai', 'sri-sathya-sai'
  UNION ALL SELECT 'Srikakulam', 'srikakulam'
  UNION ALL SELECT 'Tirupati', 'tirupati'
  UNION ALL SELECT 'Visakhapatnam', 'visakhapatnam'
  UNION ALL SELECT 'Vizianagaram', 'vizianagaram'
  UNION ALL SELECT 'West Godavari', 'west-godavari'
  UNION ALL SELECT 'YSR Kadapa', 'ysr-kadapa'
) v WHERE s.country_id = 1 AND s.slug = 'andhra-pradesh';

-- Arunachal Pradesh: 27 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Anjaw' AS name, 'anjaw' AS slug
  UNION ALL SELECT 'Bichom', 'bichom'
  UNION ALL SELECT 'Changlang', 'changlang'
  UNION ALL SELECT 'Dibang Valley', 'dibang-valley'
  UNION ALL SELECT 'East Kameng', 'east-kameng'
  UNION ALL SELECT 'East Siang', 'east-siang'
  UNION ALL SELECT 'Kamle', 'kamle'
  UNION ALL SELECT 'Keyi Panyor', 'keyi-panyor'
  UNION ALL SELECT 'Kra Daadi', 'kra-daadi'
  UNION ALL SELECT 'Kurung Kumey', 'kurung-kumey'
  UNION ALL SELECT 'Lepa Rada', 'lepa-rada'
  UNION ALL SELECT 'Lohit', 'lohit'
  UNION ALL SELECT 'Longding', 'longding'
  UNION ALL SELECT 'Lower Dibang Valley', 'lower-dibang-valley'
  UNION ALL SELECT 'Lower Siang', 'lower-siang'
  UNION ALL SELECT 'Lower Subansiri', 'lower-subansiri'
  UNION ALL SELECT 'Namsai', 'namsai'
  UNION ALL SELECT 'Pakke-Kessang', 'pakke-kessang'
  UNION ALL SELECT 'Papum Pare', 'papum-pare'
  UNION ALL SELECT 'Shi Yomi', 'shi-yomi'
  UNION ALL SELECT 'Siang', 'siang'
  UNION ALL SELECT 'Tawang', 'tawang'
  UNION ALL SELECT 'Tirap', 'tirap'
  UNION ALL SELECT 'Upper Siang', 'upper-siang'
  UNION ALL SELECT 'Upper Subansiri', 'upper-subansiri'
  UNION ALL SELECT 'West Kameng', 'west-kameng'
  UNION ALL SELECT 'West Siang', 'west-siang'
) v WHERE s.country_id = 1 AND s.slug = 'arunachal-pradesh';

-- Assam: 35 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Bajali' AS name, 'bajali' AS slug
  UNION ALL SELECT 'Baksa', 'baksa'
  UNION ALL SELECT 'Barpeta', 'barpeta'
  UNION ALL SELECT 'Biswanath', 'biswanath'
  UNION ALL SELECT 'Bongaigaon', 'bongaigaon'
  UNION ALL SELECT 'Cachar', 'cachar'
  UNION ALL SELECT 'Charaideo', 'charaideo'
  UNION ALL SELECT 'Chirang', 'chirang'
  UNION ALL SELECT 'Darrang', 'darrang'
  UNION ALL SELECT 'Dhemaji', 'dhemaji'
  UNION ALL SELECT 'Dhubri', 'dhubri'
  UNION ALL SELECT 'Dibrugarh', 'dibrugarh'
  UNION ALL SELECT 'Dima Hasao', 'dima-hasao'
  UNION ALL SELECT 'Goalpara', 'goalpara'
  UNION ALL SELECT 'Golaghat', 'golaghat'
  UNION ALL SELECT 'Hailakandi', 'hailakandi'
  UNION ALL SELECT 'Hojai', 'hojai'
  UNION ALL SELECT 'Jorhat', 'jorhat'
  UNION ALL SELECT 'Kamrup', 'kamrup'
  UNION ALL SELECT 'Kamrup Metropolitan', 'kamrup-metropolitan'
  UNION ALL SELECT 'Karbi Anglong', 'karbi-anglong'
  UNION ALL SELECT 'Kokrajhar', 'kokrajhar'
  UNION ALL SELECT 'Lakhimpur', 'lakhimpur'
  UNION ALL SELECT 'Majuli', 'majuli'
  UNION ALL SELECT 'Morigaon', 'morigaon'
  UNION ALL SELECT 'Nagaon', 'nagaon'
  UNION ALL SELECT 'Nalbari', 'nalbari'
  UNION ALL SELECT 'Sivasagar', 'sivasagar'
  UNION ALL SELECT 'Sonitpur', 'sonitpur'
  UNION ALL SELECT 'South Salmara-Mankachar', 'south-salmara-mankachar'
  UNION ALL SELECT 'Sribhumi', 'sribhumi'
  UNION ALL SELECT 'Tamulpur', 'tamulpur'
  UNION ALL SELECT 'Tinsukia', 'tinsukia'
  UNION ALL SELECT 'Udalguri', 'udalguri'
  UNION ALL SELECT 'West Karbi Anglong', 'west-karbi-anglong'
) v WHERE s.country_id = 1 AND s.slug = 'assam';

-- Bihar: 38 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Araria' AS name, 'araria' AS slug
  UNION ALL SELECT 'Arwal', 'arwal'
  UNION ALL SELECT 'Aurangabad', 'aurangabad'
  UNION ALL SELECT 'Banka', 'banka'
  UNION ALL SELECT 'Begusarai', 'begusarai'
  UNION ALL SELECT 'Bhagalpur', 'bhagalpur'
  UNION ALL SELECT 'Bhojpur', 'bhojpur'
  UNION ALL SELECT 'Buxar', 'buxar'
  UNION ALL SELECT 'Darbhanga', 'darbhanga'
  UNION ALL SELECT 'East Champaran', 'east-champaran'
  UNION ALL SELECT 'Gaya', 'gaya'
  UNION ALL SELECT 'Gopalganj', 'gopalganj'
  UNION ALL SELECT 'Jamui', 'jamui'
  UNION ALL SELECT 'Jehanabad', 'jehanabad'
  UNION ALL SELECT 'Kaimur', 'kaimur'
  UNION ALL SELECT 'Katihar', 'katihar'
  UNION ALL SELECT 'Khagaria', 'khagaria'
  UNION ALL SELECT 'Kishanganj', 'kishanganj'
  UNION ALL SELECT 'Lakhisarai', 'lakhisarai'
  UNION ALL SELECT 'Madhepura', 'madhepura'
  UNION ALL SELECT 'Madhubani', 'madhubani'
  UNION ALL SELECT 'Munger', 'munger'
  UNION ALL SELECT 'Muzaffarpur', 'muzaffarpur'
  UNION ALL SELECT 'Nalanda', 'nalanda'
  UNION ALL SELECT 'Nawada', 'nawada'
  UNION ALL SELECT 'Patna', 'patna'
  UNION ALL SELECT 'Purnia', 'purnia'
  UNION ALL SELECT 'Rohtas', 'rohtas'
  UNION ALL SELECT 'Saharsa', 'saharsa'
  UNION ALL SELECT 'Samastipur', 'samastipur'
  UNION ALL SELECT 'Saran', 'saran'
  UNION ALL SELECT 'Sheikhpura', 'sheikhpura'
  UNION ALL SELECT 'Sheohar', 'sheohar'
  UNION ALL SELECT 'Sitamarhi', 'sitamarhi'
  UNION ALL SELECT 'Siwan', 'siwan'
  UNION ALL SELECT 'Supaul', 'supaul'
  UNION ALL SELECT 'Vaishali', 'vaishali'
  UNION ALL SELECT 'West Champaran', 'west-champaran'
) v WHERE s.country_id = 1 AND s.slug = 'bihar';

-- Chhattisgarh: 33 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Balod' AS name, 'balod' AS slug
  UNION ALL SELECT 'Baloda Bazar', 'baloda-bazar'
  UNION ALL SELECT 'Balrampur-Ramanujganj', 'balrampur-ramanujganj'
  UNION ALL SELECT 'Bastar', 'bastar'
  UNION ALL SELECT 'Bemetara', 'bemetara'
  UNION ALL SELECT 'Bijapur', 'bijapur'
  UNION ALL SELECT 'Bilaspur', 'bilaspur-chhattisgarh'
  UNION ALL SELECT 'Dantewada', 'dantewada'
  UNION ALL SELECT 'Dhamtari', 'dhamtari'
  UNION ALL SELECT 'Durg', 'durg'
  UNION ALL SELECT 'Gariaband', 'gariaband'
  UNION ALL SELECT 'Gaurela-Pendra-Marwahi', 'gaurela-pendra-marwahi'
  UNION ALL SELECT 'Janjgir-Champa', 'janjgir-champa'
  UNION ALL SELECT 'Jashpur', 'jashpur'
  UNION ALL SELECT 'Kabirdham', 'kabirdham'
  UNION ALL SELECT 'Kanker', 'kanker'
  UNION ALL SELECT 'Khairagarh-Chhuikhadan-Gandai', 'khairagarh-chhuikhadan-gandai'
  UNION ALL SELECT 'Kondagaon', 'kondagaon'
  UNION ALL SELECT 'Korba', 'korba'
  UNION ALL SELECT 'Koriya', 'koriya'
  UNION ALL SELECT 'Mahasamund', 'mahasamund'
  UNION ALL SELECT 'Manendragarh-Chirmiri-Bharatpur', 'manendragarh-chirmiri-bharatpur'
  UNION ALL SELECT 'Mohla-Manpur-Ambagarh Chowki', 'mohla-manpur-ambagarh-chowki'
  UNION ALL SELECT 'Mungeli', 'mungeli'
  UNION ALL SELECT 'Narayanpur', 'narayanpur'
  UNION ALL SELECT 'Raigarh', 'raigarh'
  UNION ALL SELECT 'Raipur', 'raipur'
  UNION ALL SELECT 'Rajnandgaon', 'rajnandgaon'
  UNION ALL SELECT 'Sakti', 'sakti'
  UNION ALL SELECT 'Sarangarh-Bilaigarh', 'sarangarh-bilaigarh'
  UNION ALL SELECT 'Sukma', 'sukma'
  UNION ALL SELECT 'Surajpur', 'surajpur'
  UNION ALL SELECT 'Surguja', 'surguja'
) v WHERE s.country_id = 1 AND s.slug = 'chhattisgarh';

-- Goa: 2 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'North Goa' AS name, 'north-goa' AS slug
  UNION ALL SELECT 'South Goa', 'south-goa'
) v WHERE s.country_id = 1 AND s.slug = 'goa';

-- Gujarat: 34 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Ahmedabad' AS name, 'ahmedabad' AS slug
  UNION ALL SELECT 'Amreli', 'amreli'
  UNION ALL SELECT 'Anand', 'anand'
  UNION ALL SELECT 'Aravalli', 'aravalli'
  UNION ALL SELECT 'Banaskantha', 'banaskantha'
  UNION ALL SELECT 'Bharuch', 'bharuch'
  UNION ALL SELECT 'Bhavnagar', 'bhavnagar'
  UNION ALL SELECT 'Botad', 'botad'
  UNION ALL SELECT 'Chhota Udaipur', 'chhota-udaipur'
  UNION ALL SELECT 'Dahod', 'dahod'
  UNION ALL SELECT 'Dang', 'dang'
  UNION ALL SELECT 'Devbhumi Dwarka', 'devbhumi-dwarka'
  UNION ALL SELECT 'Gandhinagar', 'gandhinagar'
  UNION ALL SELECT 'Gir Somnath', 'gir-somnath'
  UNION ALL SELECT 'Jamnagar', 'jamnagar'
  UNION ALL SELECT 'Junagadh', 'junagadh'
  UNION ALL SELECT 'Kheda', 'kheda'
  UNION ALL SELECT 'Kutch', 'kutch'
  UNION ALL SELECT 'Mahisagar', 'mahisagar'
  UNION ALL SELECT 'Mehsana', 'mehsana'
  UNION ALL SELECT 'Morbi', 'morbi'
  UNION ALL SELECT 'Narmada', 'narmada'
  UNION ALL SELECT 'Navsari', 'navsari'
  UNION ALL SELECT 'Panchmahal', 'panchmahal'
  UNION ALL SELECT 'Patan', 'patan'
  UNION ALL SELECT 'Porbandar', 'porbandar'
  UNION ALL SELECT 'Rajkot', 'rajkot'
  UNION ALL SELECT 'Sabarkantha', 'sabarkantha'
  UNION ALL SELECT 'Surat', 'surat'
  UNION ALL SELECT 'Surendranagar', 'surendranagar'
  UNION ALL SELECT 'Tapi', 'tapi'
  UNION ALL SELECT 'Vadodara', 'vadodara'
  UNION ALL SELECT 'Valsad', 'valsad'
  UNION ALL SELECT 'Vav-Tharad', 'vav-tharad'
) v WHERE s.country_id = 1 AND s.slug = 'gujarat';

-- Haryana: 22 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Ambala' AS name, 'ambala' AS slug
  UNION ALL SELECT 'Bhiwani', 'bhiwani'
  UNION ALL SELECT 'Charkhi Dadri', 'charkhi-dadri'
  UNION ALL SELECT 'Faridabad', 'faridabad'
  UNION ALL SELECT 'Fatehabad', 'fatehabad'
  UNION ALL SELECT 'Gurugram', 'gurugram'
  UNION ALL SELECT 'Hisar', 'hisar'
  UNION ALL SELECT 'Jhajjar', 'jhajjar'
  UNION ALL SELECT 'Jind', 'jind'
  UNION ALL SELECT 'Kaithal', 'kaithal'
  UNION ALL SELECT 'Karnal', 'karnal'
  UNION ALL SELECT 'Kurukshetra', 'kurukshetra'
  UNION ALL SELECT 'Mahendragarh', 'mahendragarh'
  UNION ALL SELECT 'Nuh', 'nuh'
  UNION ALL SELECT 'Palwal', 'palwal'
  UNION ALL SELECT 'Panchkula', 'panchkula'
  UNION ALL SELECT 'Panipat', 'panipat'
  UNION ALL SELECT 'Rewari', 'rewari'
  UNION ALL SELECT 'Rohtak', 'rohtak'
  UNION ALL SELECT 'Sirsa', 'sirsa'
  UNION ALL SELECT 'Sonipat', 'sonipat'
  UNION ALL SELECT 'Yamunanagar', 'yamunanagar'
) v WHERE s.country_id = 1 AND s.slug = 'haryana';

-- Himachal Pradesh: 12 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Bilaspur' AS name, 'bilaspur-himachal-pradesh' AS slug
  UNION ALL SELECT 'Chamba', 'chamba'
  UNION ALL SELECT 'Hamirpur', 'hamirpur-himachal-pradesh'
  UNION ALL SELECT 'Kangra', 'kangra'
  UNION ALL SELECT 'Kinnaur', 'kinnaur'
  UNION ALL SELECT 'Kullu', 'kullu'
  UNION ALL SELECT 'Lahaul and Spiti', 'lahaul-and-spiti'
  UNION ALL SELECT 'Mandi', 'mandi'
  UNION ALL SELECT 'Shimla', 'shimla'
  UNION ALL SELECT 'Sirmaur', 'sirmaur'
  UNION ALL SELECT 'Solan', 'solan'
  UNION ALL SELECT 'Una', 'una'
) v WHERE s.country_id = 1 AND s.slug = 'himachal-pradesh';

-- Jharkhand: 24 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Bokaro' AS name, 'bokaro' AS slug
  UNION ALL SELECT 'Chatra', 'chatra'
  UNION ALL SELECT 'Deoghar', 'deoghar'
  UNION ALL SELECT 'Dhanbad', 'dhanbad'
  UNION ALL SELECT 'Dumka', 'dumka'
  UNION ALL SELECT 'East Singhbhum', 'east-singhbhum'
  UNION ALL SELECT 'Garhwa', 'garhwa'
  UNION ALL SELECT 'Giridih', 'giridih'
  UNION ALL SELECT 'Godda', 'godda'
  UNION ALL SELECT 'Gumla', 'gumla'
  UNION ALL SELECT 'Hazaribagh', 'hazaribagh'
  UNION ALL SELECT 'Jamtara', 'jamtara'
  UNION ALL SELECT 'Khunti', 'khunti'
  UNION ALL SELECT 'Koderma', 'koderma'
  UNION ALL SELECT 'Latehar', 'latehar'
  UNION ALL SELECT 'Lohardaga', 'lohardaga'
  UNION ALL SELECT 'Pakur', 'pakur'
  UNION ALL SELECT 'Palamu', 'palamu'
  UNION ALL SELECT 'Ramgarh', 'ramgarh'
  UNION ALL SELECT 'Ranchi', 'ranchi'
  UNION ALL SELECT 'Sahibganj', 'sahibganj'
  UNION ALL SELECT 'Seraikela Kharsawan', 'seraikela-kharsawan'
  UNION ALL SELECT 'Simdega', 'simdega'
  UNION ALL SELECT 'West Singhbhum', 'west-singhbhum'
) v WHERE s.country_id = 1 AND s.slug = 'jharkhand';

-- Karnataka: 31 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Bagalkot' AS name, 'bagalkot' AS slug
  UNION ALL SELECT 'Ballari', 'ballari'
  UNION ALL SELECT 'Belagavi', 'belagavi'
  UNION ALL SELECT 'Bengaluru Rural', 'bengaluru-rural'
  UNION ALL SELECT 'Bengaluru South', 'bengaluru-south'
  UNION ALL SELECT 'Bengaluru Urban', 'bengaluru-urban'
  UNION ALL SELECT 'Bidar', 'bidar'
  UNION ALL SELECT 'Chamarajanagar', 'chamarajanagar'
  UNION ALL SELECT 'Chikkaballapur', 'chikkaballapur'
  UNION ALL SELECT 'Chikkamagaluru', 'chikkamagaluru'
  UNION ALL SELECT 'Chitradurga', 'chitradurga'
  UNION ALL SELECT 'Dakshina Kannada', 'dakshina-kannada'
  UNION ALL SELECT 'Davanagere', 'davanagere'
  UNION ALL SELECT 'Dharwad', 'dharwad'
  UNION ALL SELECT 'Gadag', 'gadag'
  UNION ALL SELECT 'Hassan', 'hassan'
  UNION ALL SELECT 'Haveri', 'haveri'
  UNION ALL SELECT 'Kalaburagi', 'kalaburagi'
  UNION ALL SELECT 'Kodagu', 'kodagu'
  UNION ALL SELECT 'Kolar', 'kolar'
  UNION ALL SELECT 'Koppal', 'koppal'
  UNION ALL SELECT 'Mandya', 'mandya'
  UNION ALL SELECT 'Mysuru', 'mysuru'
  UNION ALL SELECT 'Raichur', 'raichur'
  UNION ALL SELECT 'Shivamogga', 'shivamogga'
  UNION ALL SELECT 'Tumakuru', 'tumakuru'
  UNION ALL SELECT 'Udupi', 'udupi'
  UNION ALL SELECT 'Uttara Kannada', 'uttara-kannada'
  UNION ALL SELECT 'Vijayanagara', 'vijayanagara'
  UNION ALL SELECT 'Vijayapura', 'vijayapura'
  UNION ALL SELECT 'Yadgir', 'yadgir'
) v WHERE s.country_id = 1 AND s.slug = 'karnataka';

-- Kerala: 14 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Alappuzha' AS name, 'alappuzha' AS slug
  UNION ALL SELECT 'Ernakulam', 'ernakulam'
  UNION ALL SELECT 'Idukki', 'idukki'
  UNION ALL SELECT 'Kannur', 'kannur'
  UNION ALL SELECT 'Kasaragod', 'kasaragod'
  UNION ALL SELECT 'Kollam', 'kollam'
  UNION ALL SELECT 'Kottayam', 'kottayam'
  UNION ALL SELECT 'Kozhikode', 'kozhikode'
  UNION ALL SELECT 'Malappuram', 'malappuram'
  UNION ALL SELECT 'Palakkad', 'palakkad'
  UNION ALL SELECT 'Pathanamthitta', 'pathanamthitta'
  UNION ALL SELECT 'Thiruvananthapuram', 'thiruvananthapuram'
  UNION ALL SELECT 'Thrissur', 'thrissur'
  UNION ALL SELECT 'Wayanad', 'wayanad'
) v WHERE s.country_id = 1 AND s.slug = 'kerala';

-- Madhya Pradesh: 55 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Agar Malwa' AS name, 'agar-malwa' AS slug
  UNION ALL SELECT 'Alirajpur', 'alirajpur'
  UNION ALL SELECT 'Anuppur', 'anuppur'
  UNION ALL SELECT 'Ashoknagar', 'ashoknagar'
  UNION ALL SELECT 'Balaghat', 'balaghat'
  UNION ALL SELECT 'Barwani', 'barwani'
  UNION ALL SELECT 'Betul', 'betul'
  UNION ALL SELECT 'Bhind', 'bhind'
  UNION ALL SELECT 'Bhopal', 'bhopal'
  UNION ALL SELECT 'Burhanpur', 'burhanpur'
  UNION ALL SELECT 'Chhatarpur', 'chhatarpur'
  UNION ALL SELECT 'Chhindwara', 'chhindwara'
  UNION ALL SELECT 'Damoh', 'damoh'
  UNION ALL SELECT 'Datia', 'datia'
  UNION ALL SELECT 'Dewas', 'dewas'
  UNION ALL SELECT 'Dhar', 'dhar'
  UNION ALL SELECT 'Dindori', 'dindori'
  UNION ALL SELECT 'Guna', 'guna'
  UNION ALL SELECT 'Gwalior', 'gwalior'
  UNION ALL SELECT 'Harda', 'harda'
  UNION ALL SELECT 'Indore', 'indore'
  UNION ALL SELECT 'Jabalpur', 'jabalpur'
  UNION ALL SELECT 'Jhabua', 'jhabua'
  UNION ALL SELECT 'Katni', 'katni'
  UNION ALL SELECT 'Khandwa', 'khandwa'
  UNION ALL SELECT 'Khargone', 'khargone'
  UNION ALL SELECT 'Maihar', 'maihar'
  UNION ALL SELECT 'Mandla', 'mandla'
  UNION ALL SELECT 'Mandsaur', 'mandsaur'
  UNION ALL SELECT 'Mauganj', 'mauganj'
  UNION ALL SELECT 'Morena', 'morena'
  UNION ALL SELECT 'Narmadapuram', 'narmadapuram'
  UNION ALL SELECT 'Narsinghpur', 'narsinghpur'
  UNION ALL SELECT 'Neemuch', 'neemuch'
  UNION ALL SELECT 'Niwari', 'niwari'
  UNION ALL SELECT 'Pandhurna', 'pandhurna'
  UNION ALL SELECT 'Panna', 'panna'
  UNION ALL SELECT 'Raisen', 'raisen'
  UNION ALL SELECT 'Rajgarh', 'rajgarh'
  UNION ALL SELECT 'Ratlam', 'ratlam'
  UNION ALL SELECT 'Rewa', 'rewa'
  UNION ALL SELECT 'Sagar', 'sagar'
  UNION ALL SELECT 'Satna', 'satna'
  UNION ALL SELECT 'Sehore', 'sehore'
  UNION ALL SELECT 'Seoni', 'seoni'
  UNION ALL SELECT 'Shahdol', 'shahdol'
  UNION ALL SELECT 'Shajapur', 'shajapur'
  UNION ALL SELECT 'Sheopur', 'sheopur'
  UNION ALL SELECT 'Shivpuri', 'shivpuri'
  UNION ALL SELECT 'Sidhi', 'sidhi'
  UNION ALL SELECT 'Singrauli', 'singrauli'
  UNION ALL SELECT 'Tikamgarh', 'tikamgarh'
  UNION ALL SELECT 'Ujjain', 'ujjain'
  UNION ALL SELECT 'Umaria', 'umaria'
  UNION ALL SELECT 'Vidisha', 'vidisha'
) v WHERE s.country_id = 1 AND s.slug = 'madhya-pradesh';

-- Maharashtra: 36 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Ahilyanagar' AS name, 'ahilyanagar' AS slug
  UNION ALL SELECT 'Akola', 'akola'
  UNION ALL SELECT 'Amravati', 'amravati'
  UNION ALL SELECT 'Beed', 'beed'
  UNION ALL SELECT 'Bhandara', 'bhandara'
  UNION ALL SELECT 'Buldhana', 'buldhana'
  UNION ALL SELECT 'Chandrapur', 'chandrapur'
  UNION ALL SELECT 'Chhatrapati Sambhajinagar', 'chhatrapati-sambhajinagar'
  UNION ALL SELECT 'Dharashiv', 'dharashiv'
  UNION ALL SELECT 'Dhule', 'dhule'
  UNION ALL SELECT 'Gadchiroli', 'gadchiroli'
  UNION ALL SELECT 'Gondia', 'gondia'
  UNION ALL SELECT 'Hingoli', 'hingoli'
  UNION ALL SELECT 'Jalgaon', 'jalgaon'
  UNION ALL SELECT 'Jalna', 'jalna'
  UNION ALL SELECT 'Kolhapur', 'kolhapur'
  UNION ALL SELECT 'Latur', 'latur'
  UNION ALL SELECT 'Mumbai City', 'mumbai-city'
  UNION ALL SELECT 'Mumbai Suburban', 'mumbai-suburban'
  UNION ALL SELECT 'Nagpur', 'nagpur'
  UNION ALL SELECT 'Nanded', 'nanded'
  UNION ALL SELECT 'Nandurbar', 'nandurbar'
  UNION ALL SELECT 'Nashik', 'nashik'
  UNION ALL SELECT 'Palghar', 'palghar'
  UNION ALL SELECT 'Parbhani', 'parbhani'
  UNION ALL SELECT 'Pune', 'pune'
  UNION ALL SELECT 'Raigad', 'raigad'
  UNION ALL SELECT 'Ratnagiri', 'ratnagiri'
  UNION ALL SELECT 'Sangli', 'sangli'
  UNION ALL SELECT 'Satara', 'satara'
  UNION ALL SELECT 'Sindhudurg', 'sindhudurg'
  UNION ALL SELECT 'Solapur', 'solapur'
  UNION ALL SELECT 'Thane', 'thane'
  UNION ALL SELECT 'Wardha', 'wardha'
  UNION ALL SELECT 'Washim', 'washim'
  UNION ALL SELECT 'Yavatmal', 'yavatmal'
) v WHERE s.country_id = 1 AND s.slug = 'maharashtra';

-- Manipur: 16 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Bishnupur' AS name, 'bishnupur' AS slug
  UNION ALL SELECT 'Chandel', 'chandel'
  UNION ALL SELECT 'Churachandpur', 'churachandpur'
  UNION ALL SELECT 'Imphal East', 'imphal-east'
  UNION ALL SELECT 'Imphal West', 'imphal-west'
  UNION ALL SELECT 'Jiribam', 'jiribam'
  UNION ALL SELECT 'Kakching', 'kakching'
  UNION ALL SELECT 'Kamjong', 'kamjong'
  UNION ALL SELECT 'Kangpokpi', 'kangpokpi'
  UNION ALL SELECT 'Noney', 'noney'
  UNION ALL SELECT 'Pherzawl', 'pherzawl'
  UNION ALL SELECT 'Senapati', 'senapati'
  UNION ALL SELECT 'Tamenglong', 'tamenglong'
  UNION ALL SELECT 'Tengnoupal', 'tengnoupal'
  UNION ALL SELECT 'Thoubal', 'thoubal'
  UNION ALL SELECT 'Ukhrul', 'ukhrul'
) v WHERE s.country_id = 1 AND s.slug = 'manipur';

-- Meghalaya: 12 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'East Garo Hills' AS name, 'east-garo-hills' AS slug
  UNION ALL SELECT 'East Jaintia Hills', 'east-jaintia-hills'
  UNION ALL SELECT 'East Khasi Hills', 'east-khasi-hills'
  UNION ALL SELECT 'Eastern West Khasi Hills', 'eastern-west-khasi-hills'
  UNION ALL SELECT 'North Garo Hills', 'north-garo-hills'
  UNION ALL SELECT 'Ri Bhoi', 'ri-bhoi'
  UNION ALL SELECT 'South Garo Hills', 'south-garo-hills'
  UNION ALL SELECT 'South West Garo Hills', 'south-west-garo-hills'
  UNION ALL SELECT 'South West Khasi Hills', 'south-west-khasi-hills'
  UNION ALL SELECT 'West Garo Hills', 'west-garo-hills'
  UNION ALL SELECT 'West Jaintia Hills', 'west-jaintia-hills'
  UNION ALL SELECT 'West Khasi Hills', 'west-khasi-hills'
) v WHERE s.country_id = 1 AND s.slug = 'meghalaya';

-- Mizoram: 11 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Aizawl' AS name, 'aizawl' AS slug
  UNION ALL SELECT 'Champhai', 'champhai'
  UNION ALL SELECT 'Hnahthial', 'hnahthial'
  UNION ALL SELECT 'Khawzawl', 'khawzawl'
  UNION ALL SELECT 'Kolasib', 'kolasib'
  UNION ALL SELECT 'Lawngtlai', 'lawngtlai'
  UNION ALL SELECT 'Lunglei', 'lunglei'
  UNION ALL SELECT 'Mamit', 'mamit'
  UNION ALL SELECT 'Saitual', 'saitual'
  UNION ALL SELECT 'Serchhip', 'serchhip'
  UNION ALL SELECT 'Siaha', 'siaha'
) v WHERE s.country_id = 1 AND s.slug = 'mizoram';

-- Nagaland: 17 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Chumoukedima' AS name, 'chumoukedima' AS slug
  UNION ALL SELECT 'Dimapur', 'dimapur'
  UNION ALL SELECT 'Kiphire', 'kiphire'
  UNION ALL SELECT 'Kohima', 'kohima'
  UNION ALL SELECT 'Longleng', 'longleng'
  UNION ALL SELECT 'Meluri', 'meluri'
  UNION ALL SELECT 'Mokokchung', 'mokokchung'
  UNION ALL SELECT 'Mon', 'mon'
  UNION ALL SELECT 'Niuland', 'niuland'
  UNION ALL SELECT 'Noklak', 'noklak'
  UNION ALL SELECT 'Peren', 'peren'
  UNION ALL SELECT 'Phek', 'phek'
  UNION ALL SELECT 'Shamator', 'shamator'
  UNION ALL SELECT 'Tseminyu', 'tseminyu'
  UNION ALL SELECT 'Tuensang', 'tuensang'
  UNION ALL SELECT 'Wokha', 'wokha'
  UNION ALL SELECT 'Zunheboto', 'zunheboto'
) v WHERE s.country_id = 1 AND s.slug = 'nagaland';

-- Odisha: 30 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Angul' AS name, 'angul' AS slug
  UNION ALL SELECT 'Balangir', 'balangir'
  UNION ALL SELECT 'Balasore', 'balasore'
  UNION ALL SELECT 'Bargarh', 'bargarh'
  UNION ALL SELECT 'Bhadrak', 'bhadrak'
  UNION ALL SELECT 'Boudh', 'boudh'
  UNION ALL SELECT 'Cuttack', 'cuttack'
  UNION ALL SELECT 'Deogarh', 'deogarh'
  UNION ALL SELECT 'Dhenkanal', 'dhenkanal'
  UNION ALL SELECT 'Gajapati', 'gajapati'
  UNION ALL SELECT 'Ganjam', 'ganjam'
  UNION ALL SELECT 'Jagatsinghpur', 'jagatsinghpur'
  UNION ALL SELECT 'Jajpur', 'jajpur'
  UNION ALL SELECT 'Jharsuguda', 'jharsuguda'
  UNION ALL SELECT 'Kalahandi', 'kalahandi'
  UNION ALL SELECT 'Kandhamal', 'kandhamal'
  UNION ALL SELECT 'Kendrapara', 'kendrapara'
  UNION ALL SELECT 'Kendujhar', 'kendujhar'
  UNION ALL SELECT 'Khordha', 'khordha'
  UNION ALL SELECT 'Koraput', 'koraput'
  UNION ALL SELECT 'Malkangiri', 'malkangiri'
  UNION ALL SELECT 'Mayurbhanj', 'mayurbhanj'
  UNION ALL SELECT 'Nabarangpur', 'nabarangpur'
  UNION ALL SELECT 'Nayagarh', 'nayagarh'
  UNION ALL SELECT 'Nuapada', 'nuapada'
  UNION ALL SELECT 'Puri', 'puri'
  UNION ALL SELECT 'Rayagada', 'rayagada'
  UNION ALL SELECT 'Sambalpur', 'sambalpur'
  UNION ALL SELECT 'Subarnapur', 'subarnapur'
  UNION ALL SELECT 'Sundargarh', 'sundargarh'
) v WHERE s.country_id = 1 AND s.slug = 'odisha';

-- Punjab: 23 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Amritsar' AS name, 'amritsar' AS slug
  UNION ALL SELECT 'Barnala', 'barnala'
  UNION ALL SELECT 'Bathinda', 'bathinda'
  UNION ALL SELECT 'Faridkot', 'faridkot'
  UNION ALL SELECT 'Fatehgarh Sahib', 'fatehgarh-sahib'
  UNION ALL SELECT 'Fazilka', 'fazilka'
  UNION ALL SELECT 'Ferozepur', 'ferozepur'
  UNION ALL SELECT 'Gurdaspur', 'gurdaspur'
  UNION ALL SELECT 'Hoshiarpur', 'hoshiarpur'
  UNION ALL SELECT 'Jalandhar', 'jalandhar'
  UNION ALL SELECT 'Kapurthala', 'kapurthala'
  UNION ALL SELECT 'Ludhiana', 'ludhiana'
  UNION ALL SELECT 'Malerkotla', 'malerkotla'
  UNION ALL SELECT 'Mansa', 'mansa'
  UNION ALL SELECT 'Moga', 'moga'
  UNION ALL SELECT 'Pathankot', 'pathankot'
  UNION ALL SELECT 'Patiala', 'patiala'
  UNION ALL SELECT 'Rupnagar', 'rupnagar'
  UNION ALL SELECT 'Sahibzada Ajit Singh Nagar', 'sahibzada-ajit-singh-nagar'
  UNION ALL SELECT 'Sangrur', 'sangrur'
  UNION ALL SELECT 'Shaheed Bhagat Singh Nagar', 'shaheed-bhagat-singh-nagar'
  UNION ALL SELECT 'Sri Muktsar Sahib', 'sri-muktsar-sahib'
  UNION ALL SELECT 'Tarn Taran', 'tarn-taran'
) v WHERE s.country_id = 1 AND s.slug = 'punjab';

-- Rajasthan: 41 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Ajmer' AS name, 'ajmer' AS slug
  UNION ALL SELECT 'Alwar', 'alwar'
  UNION ALL SELECT 'Balotra', 'balotra'
  UNION ALL SELECT 'Banswara', 'banswara'
  UNION ALL SELECT 'Baran', 'baran'
  UNION ALL SELECT 'Barmer', 'barmer'
  UNION ALL SELECT 'Beawar', 'beawar'
  UNION ALL SELECT 'Bharatpur', 'bharatpur'
  UNION ALL SELECT 'Bhilwara', 'bhilwara'
  UNION ALL SELECT 'Bikaner', 'bikaner'
  UNION ALL SELECT 'Bundi', 'bundi'
  UNION ALL SELECT 'Chittorgarh', 'chittorgarh'
  UNION ALL SELECT 'Churu', 'churu'
  UNION ALL SELECT 'Dausa', 'dausa'
  UNION ALL SELECT 'Deeg', 'deeg'
  UNION ALL SELECT 'Dholpur', 'dholpur'
  UNION ALL SELECT 'Didwana-Kuchaman', 'didwana-kuchaman'
  UNION ALL SELECT 'Dungarpur', 'dungarpur'
  UNION ALL SELECT 'Hanumangarh', 'hanumangarh'
  UNION ALL SELECT 'Jaipur', 'jaipur'
  UNION ALL SELECT 'Jaisalmer', 'jaisalmer'
  UNION ALL SELECT 'Jalore', 'jalore'
  UNION ALL SELECT 'Jhalawar', 'jhalawar'
  UNION ALL SELECT 'Jhunjhunu', 'jhunjhunu'
  UNION ALL SELECT 'Jodhpur', 'jodhpur'
  UNION ALL SELECT 'Karauli', 'karauli'
  UNION ALL SELECT 'Khairthal-Tijara', 'khairthal-tijara'
  UNION ALL SELECT 'Kota', 'kota'
  UNION ALL SELECT 'Kotputli-Behror', 'kotputli-behror'
  UNION ALL SELECT 'Nagaur', 'nagaur'
  UNION ALL SELECT 'Pali', 'pali'
  UNION ALL SELECT 'Phalodi', 'phalodi'
  UNION ALL SELECT 'Pratapgarh', 'pratapgarh-rajasthan'
  UNION ALL SELECT 'Rajsamand', 'rajsamand'
  UNION ALL SELECT 'Salumbar', 'salumbar'
  UNION ALL SELECT 'Sawai Madhopur', 'sawai-madhopur'
  UNION ALL SELECT 'Sikar', 'sikar'
  UNION ALL SELECT 'Sirohi', 'sirohi'
  UNION ALL SELECT 'Sri Ganganagar', 'sri-ganganagar'
  UNION ALL SELECT 'Tonk', 'tonk'
  UNION ALL SELECT 'Udaipur', 'udaipur'
) v WHERE s.country_id = 1 AND s.slug = 'rajasthan';

-- Sikkim: 6 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Gangtok' AS name, 'gangtok' AS slug
  UNION ALL SELECT 'Gyalshing', 'gyalshing'
  UNION ALL SELECT 'Mangan', 'mangan'
  UNION ALL SELECT 'Namchi', 'namchi'
  UNION ALL SELECT 'Pakyong', 'pakyong'
  UNION ALL SELECT 'Soreng', 'soreng'
) v WHERE s.country_id = 1 AND s.slug = 'sikkim';

-- Telangana: 33 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Adilabad' AS name, 'adilabad' AS slug
  UNION ALL SELECT 'Bhadradri Kothagudem', 'bhadradri-kothagudem'
  UNION ALL SELECT 'Hanumakonda', 'hanumakonda'
  UNION ALL SELECT 'Hyderabad', 'hyderabad'
  UNION ALL SELECT 'Jagtial', 'jagtial'
  UNION ALL SELECT 'Jangaon', 'jangaon'
  UNION ALL SELECT 'Jayashankar Bhupalpally', 'jayashankar-bhupalpally'
  UNION ALL SELECT 'Jogulamba Gadwal', 'jogulamba-gadwal'
  UNION ALL SELECT 'Kamareddy', 'kamareddy'
  UNION ALL SELECT 'Karimnagar', 'karimnagar'
  UNION ALL SELECT 'Khammam', 'khammam'
  UNION ALL SELECT 'Kumuram Bheem Asifabad', 'kumuram-bheem-asifabad'
  UNION ALL SELECT 'Mahabubabad', 'mahabubabad'
  UNION ALL SELECT 'Mahabubnagar', 'mahabubnagar'
  UNION ALL SELECT 'Mancherial', 'mancherial'
  UNION ALL SELECT 'Medak', 'medak'
  UNION ALL SELECT 'Medchal-Malkajgiri', 'medchal-malkajgiri'
  UNION ALL SELECT 'Mulugu', 'mulugu'
  UNION ALL SELECT 'Nagarkurnool', 'nagarkurnool'
  UNION ALL SELECT 'Nalgonda', 'nalgonda'
  UNION ALL SELECT 'Narayanpet', 'narayanpet'
  UNION ALL SELECT 'Nirmal', 'nirmal'
  UNION ALL SELECT 'Nizamabad', 'nizamabad'
  UNION ALL SELECT 'Peddapalli', 'peddapalli'
  UNION ALL SELECT 'Rajanna Sircilla', 'rajanna-sircilla'
  UNION ALL SELECT 'Ranga Reddy', 'ranga-reddy'
  UNION ALL SELECT 'Sangareddy', 'sangareddy'
  UNION ALL SELECT 'Siddipet', 'siddipet'
  UNION ALL SELECT 'Suryapet', 'suryapet'
  UNION ALL SELECT 'Vikarabad', 'vikarabad'
  UNION ALL SELECT 'Wanaparthy', 'wanaparthy'
  UNION ALL SELECT 'Warangal', 'warangal'
  UNION ALL SELECT 'Yadadri Bhuvanagiri', 'yadadri-bhuvanagiri'
) v WHERE s.country_id = 1 AND s.slug = 'telangana';

-- Tripura: 8 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Dhalai' AS name, 'dhalai' AS slug
  UNION ALL SELECT 'Gomati', 'gomati'
  UNION ALL SELECT 'Khowai', 'khowai'
  UNION ALL SELECT 'North Tripura', 'north-tripura'
  UNION ALL SELECT 'Sepahijala', 'sepahijala'
  UNION ALL SELECT 'South Tripura', 'south-tripura'
  UNION ALL SELECT 'Unakoti', 'unakoti'
  UNION ALL SELECT 'West Tripura', 'west-tripura'
) v WHERE s.country_id = 1 AND s.slug = 'tripura';

-- Uttar Pradesh: 75 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Agra' AS name, 'agra' AS slug
  UNION ALL SELECT 'Aligarh', 'aligarh'
  UNION ALL SELECT 'Ambedkar Nagar', 'ambedkar-nagar'
  UNION ALL SELECT 'Amethi', 'amethi'
  UNION ALL SELECT 'Amroha', 'amroha'
  UNION ALL SELECT 'Auraiya', 'auraiya'
  UNION ALL SELECT 'Ayodhya', 'ayodhya'
  UNION ALL SELECT 'Azamgarh', 'azamgarh'
  UNION ALL SELECT 'Baghpat', 'baghpat'
  UNION ALL SELECT 'Bahraich', 'bahraich'
  UNION ALL SELECT 'Ballia', 'ballia'
  UNION ALL SELECT 'Balrampur', 'balrampur'
  UNION ALL SELECT 'Banda', 'banda'
  UNION ALL SELECT 'Barabanki', 'barabanki'
  UNION ALL SELECT 'Bareilly', 'bareilly'
  UNION ALL SELECT 'Basti', 'basti'
  UNION ALL SELECT 'Bhadohi', 'bhadohi'
  UNION ALL SELECT 'Bijnor', 'bijnor'
  UNION ALL SELECT 'Budaun', 'budaun'
  UNION ALL SELECT 'Bulandshahr', 'bulandshahr'
  UNION ALL SELECT 'Chandauli', 'chandauli'
  UNION ALL SELECT 'Chitrakoot', 'chitrakoot'
  UNION ALL SELECT 'Deoria', 'deoria'
  UNION ALL SELECT 'Etah', 'etah'
  UNION ALL SELECT 'Etawah', 'etawah'
  UNION ALL SELECT 'Farrukhabad', 'farrukhabad'
  UNION ALL SELECT 'Fatehpur', 'fatehpur'
  UNION ALL SELECT 'Firozabad', 'firozabad'
  UNION ALL SELECT 'Gautam Buddh Nagar', 'gautam-buddh-nagar'
  UNION ALL SELECT 'Ghaziabad', 'ghaziabad'
  UNION ALL SELECT 'Ghazipur', 'ghazipur'
  UNION ALL SELECT 'Gonda', 'gonda'
  UNION ALL SELECT 'Gorakhpur', 'gorakhpur'
  UNION ALL SELECT 'Hamirpur', 'hamirpur-uttar-pradesh'
  UNION ALL SELECT 'Hapur', 'hapur'
  UNION ALL SELECT 'Hardoi', 'hardoi'
  UNION ALL SELECT 'Hathras', 'hathras'
  UNION ALL SELECT 'Jalaun', 'jalaun'
  UNION ALL SELECT 'Jaunpur', 'jaunpur'
  UNION ALL SELECT 'Jhansi', 'jhansi'
  UNION ALL SELECT 'Kannauj', 'kannauj'
  UNION ALL SELECT 'Kanpur Dehat', 'kanpur-dehat'
  UNION ALL SELECT 'Kanpur Nagar', 'kanpur-nagar'
  UNION ALL SELECT 'Kasganj', 'kasganj'
  UNION ALL SELECT 'Kaushambi', 'kaushambi'
  UNION ALL SELECT 'Kheri', 'kheri'
  UNION ALL SELECT 'Kushinagar', 'kushinagar'
  UNION ALL SELECT 'Lalitpur', 'lalitpur'
  UNION ALL SELECT 'Lucknow', 'lucknow'
  UNION ALL SELECT 'Maharajganj', 'maharajganj'
  UNION ALL SELECT 'Mahoba', 'mahoba'
  UNION ALL SELECT 'Mainpuri', 'mainpuri'
  UNION ALL SELECT 'Mathura', 'mathura'
  UNION ALL SELECT 'Mau', 'mau'
  UNION ALL SELECT 'Meerut', 'meerut'
  UNION ALL SELECT 'Mirzapur', 'mirzapur'
  UNION ALL SELECT 'Moradabad', 'moradabad'
  UNION ALL SELECT 'Muzaffarnagar', 'muzaffarnagar'
  UNION ALL SELECT 'Pilibhit', 'pilibhit'
  UNION ALL SELECT 'Pratapgarh', 'pratapgarh-uttar-pradesh'
  UNION ALL SELECT 'Prayagraj', 'prayagraj'
  UNION ALL SELECT 'Raebareli', 'raebareli'
  UNION ALL SELECT 'Rampur', 'rampur'
  UNION ALL SELECT 'Saharanpur', 'saharanpur'
  UNION ALL SELECT 'Sambhal', 'sambhal'
  UNION ALL SELECT 'Sant Kabir Nagar', 'sant-kabir-nagar'
  UNION ALL SELECT 'Shahjahanpur', 'shahjahanpur'
  UNION ALL SELECT 'Shamli', 'shamli'
  UNION ALL SELECT 'Shravasti', 'shravasti'
  UNION ALL SELECT 'Siddharthnagar', 'siddharthnagar'
  UNION ALL SELECT 'Sitapur', 'sitapur'
  UNION ALL SELECT 'Sonbhadra', 'sonbhadra'
  UNION ALL SELECT 'Sultanpur', 'sultanpur'
  UNION ALL SELECT 'Unnao', 'unnao'
  UNION ALL SELECT 'Varanasi', 'varanasi'
) v WHERE s.country_id = 1 AND s.slug = 'uttar-pradesh';

-- Uttarakhand: 13 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Almora' AS name, 'almora' AS slug
  UNION ALL SELECT 'Bageshwar', 'bageshwar'
  UNION ALL SELECT 'Chamoli', 'chamoli'
  UNION ALL SELECT 'Champawat', 'champawat'
  UNION ALL SELECT 'Dehradun', 'dehradun'
  UNION ALL SELECT 'Haridwar', 'haridwar'
  UNION ALL SELECT 'Nainital', 'nainital'
  UNION ALL SELECT 'Pauri Garhwal', 'pauri-garhwal'
  UNION ALL SELECT 'Pithoragarh', 'pithoragarh'
  UNION ALL SELECT 'Rudraprayag', 'rudraprayag'
  UNION ALL SELECT 'Tehri Garhwal', 'tehri-garhwal'
  UNION ALL SELECT 'Udham Singh Nagar', 'udham-singh-nagar'
  UNION ALL SELECT 'Uttarkashi', 'uttarkashi'
) v WHERE s.country_id = 1 AND s.slug = 'uttarakhand';

-- West Bengal: 23 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Alipurduar' AS name, 'alipurduar' AS slug
  UNION ALL SELECT 'Bankura', 'bankura'
  UNION ALL SELECT 'Birbhum', 'birbhum'
  UNION ALL SELECT 'Cooch Behar', 'cooch-behar'
  UNION ALL SELECT 'Dakshin Dinajpur', 'dakshin-dinajpur'
  UNION ALL SELECT 'Darjeeling', 'darjeeling'
  UNION ALL SELECT 'Hooghly', 'hooghly'
  UNION ALL SELECT 'Howrah', 'howrah'
  UNION ALL SELECT 'Jalpaiguri', 'jalpaiguri'
  UNION ALL SELECT 'Jhargram', 'jhargram'
  UNION ALL SELECT 'Kalimpong', 'kalimpong'
  UNION ALL SELECT 'Kolkata', 'kolkata'
  UNION ALL SELECT 'Malda', 'malda'
  UNION ALL SELECT 'Murshidabad', 'murshidabad'
  UNION ALL SELECT 'Nadia', 'nadia'
  UNION ALL SELECT 'North 24 Parganas', 'north-24-parganas'
  UNION ALL SELECT 'Paschim Bardhaman', 'paschim-bardhaman'
  UNION ALL SELECT 'Paschim Medinipur', 'paschim-medinipur'
  UNION ALL SELECT 'Purba Bardhaman', 'purba-bardhaman'
  UNION ALL SELECT 'Purba Medinipur', 'purba-medinipur'
  UNION ALL SELECT 'Purulia', 'purulia'
  UNION ALL SELECT 'South 24 Parganas', 'south-24-parganas'
  UNION ALL SELECT 'Uttar Dinajpur', 'uttar-dinajpur'
) v WHERE s.country_id = 1 AND s.slug = 'west-bengal';

-- Andaman and Nicobar Islands: 3 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Nicobar' AS name, 'nicobar' AS slug
  UNION ALL SELECT 'North and Middle Andaman', 'north-and-middle-andaman'
  UNION ALL SELECT 'South Andaman', 'south-andaman'
) v WHERE s.country_id = 1 AND s.slug = 'andaman-and-nicobar-islands';

-- Chandigarh: 1 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Chandigarh' AS name, 'chandigarh' AS slug
) v WHERE s.country_id = 1 AND s.slug = 'chandigarh';

-- Dadra and Nagar Haveli and Daman and Diu: 3 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Dadra and Nagar Haveli' AS name, 'dadra-and-nagar-haveli' AS slug
  UNION ALL SELECT 'Daman', 'daman'
  UNION ALL SELECT 'Diu', 'diu'
) v WHERE s.country_id = 1 AND s.slug = 'dadra-and-nagar-haveli-and-daman-and-diu';

-- Delhi: 11 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Central Delhi' AS name, 'central-delhi' AS slug
  UNION ALL SELECT 'East Delhi', 'east-delhi'
  UNION ALL SELECT 'New Delhi', 'new-delhi'
  UNION ALL SELECT 'North Delhi', 'north-delhi'
  UNION ALL SELECT 'North East Delhi', 'north-east-delhi'
  UNION ALL SELECT 'North West Delhi', 'north-west-delhi'
  UNION ALL SELECT 'Shahdara', 'shahdara'
  UNION ALL SELECT 'South Delhi', 'south-delhi'
  UNION ALL SELECT 'South East Delhi', 'south-east-delhi'
  UNION ALL SELECT 'South West Delhi', 'south-west-delhi'
  UNION ALL SELECT 'West Delhi', 'west-delhi'
) v WHERE s.country_id = 1 AND s.slug = 'delhi';

-- Jammu and Kashmir: 20 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Anantnag' AS name, 'anantnag' AS slug
  UNION ALL SELECT 'Bandipora', 'bandipora'
  UNION ALL SELECT 'Baramulla', 'baramulla'
  UNION ALL SELECT 'Budgam', 'budgam'
  UNION ALL SELECT 'Doda', 'doda'
  UNION ALL SELECT 'Ganderbal', 'ganderbal'
  UNION ALL SELECT 'Jammu', 'jammu'
  UNION ALL SELECT 'Kathua', 'kathua'
  UNION ALL SELECT 'Kishtwar', 'kishtwar'
  UNION ALL SELECT 'Kulgam', 'kulgam'
  UNION ALL SELECT 'Kupwara', 'kupwara'
  UNION ALL SELECT 'Poonch', 'poonch'
  UNION ALL SELECT 'Pulwama', 'pulwama'
  UNION ALL SELECT 'Rajouri', 'rajouri'
  UNION ALL SELECT 'Ramban', 'ramban'
  UNION ALL SELECT 'Reasi', 'reasi'
  UNION ALL SELECT 'Samba', 'samba'
  UNION ALL SELECT 'Shopian', 'shopian'
  UNION ALL SELECT 'Srinagar', 'srinagar'
  UNION ALL SELECT 'Udhampur', 'udhampur'
) v WHERE s.country_id = 1 AND s.slug = 'jammu-and-kashmir';

-- Ladakh: 2 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Kargil' AS name, 'kargil' AS slug
  UNION ALL SELECT 'Leh', 'leh'
) v WHERE s.country_id = 1 AND s.slug = 'ladakh';

-- Lakshadweep: 1 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Lakshadweep' AS name, 'lakshadweep' AS slug
) v WHERE s.country_id = 1 AND s.slug = 'lakshadweep';

-- Puducherry: 4 districts
INSERT IGNORE INTO districts (state_id, name, slug, is_active)
SELECT s.id, v.name, v.slug, 1 FROM states s JOIN (
SELECT 'Puducherry' AS name, 'puducherry' AS slug
  UNION ALL SELECT 'Karaikal', 'karaikal'
  UNION ALL SELECT 'Mahe', 'mahe'
  UNION ALL SELECT 'Yanam', 'yanam'
) v WHERE s.country_id = 1 AND s.slug = 'puducherry';

-- Cities and major places (973)
INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'alluri-sitharama-raju' AS dslug, 'Paderu' AS name, 'paderu' AS slug, 0 AS sort_order
  UNION ALL SELECT 'anakapalli', 'Anakapalli', 'anakapalli', 0
  UNION ALL SELECT 'anantapur', 'Anantapur', 'anantapur', 0
  UNION ALL SELECT 'annamayya', 'Rayachoti', 'rayachoti', 0
  UNION ALL SELECT 'bapatla', 'Bapatla', 'bapatla', 0
  UNION ALL SELECT 'chittoor', 'Chittoor', 'chittoor', 0
  UNION ALL SELECT 'dr-br-ambedkar-konaseema', 'Amalapuram', 'amalapuram', 0
  UNION ALL SELECT 'east-godavari', 'Rajamahendravaram', 'rajamahendravaram', 0
  UNION ALL SELECT 'eluru', 'Eluru', 'eluru', 0
  UNION ALL SELECT 'guntur', 'Guntur', 'guntur', 0
  UNION ALL SELECT 'kakinada', 'Kakinada', 'kakinada', 0
  UNION ALL SELECT 'krishna', 'Machilipatnam', 'machilipatnam', 0
  UNION ALL SELECT 'kurnool', 'Kurnool', 'kurnool', 0
  UNION ALL SELECT 'nandyal', 'Nandyal', 'nandyal', 0
  UNION ALL SELECT 'ntr', 'Vijayawada', 'vijayawada', 0
  UNION ALL SELECT 'palnadu', 'Narasaraopet', 'narasaraopet', 0
  UNION ALL SELECT 'parvathipuram-manyam', 'Parvathipuram', 'parvathipuram', 0
  UNION ALL SELECT 'prakasam', 'Ongole', 'ongole', 0
  UNION ALL SELECT 'sri-potti-sriramulu-nellore', 'Nellore', 'nellore', 0
  UNION ALL SELECT 'sri-sathya-sai', 'Puttaparthi', 'puttaparthi', 0
  UNION ALL SELECT 'srikakulam', 'Srikakulam', 'srikakulam', 0
  UNION ALL SELECT 'tirupati', 'Tirupati', 'tirupati', 0
  UNION ALL SELECT 'visakhapatnam', 'Visakhapatnam', 'visakhapatnam', 0
  UNION ALL SELECT 'vizianagaram', 'Vizianagaram', 'vizianagaram', 0
  UNION ALL SELECT 'west-godavari', 'Bhimavaram', 'bhimavaram', 0
  UNION ALL SELECT 'ysr-kadapa', 'Kadapa', 'kadapa', 0
  UNION ALL SELECT 'alluri-sitharama-raju', 'Araku Valley', 'araku-valley', 10
  UNION ALL SELECT 'guntur', 'Amaravati', 'amaravati', 11
  UNION ALL SELECT 'guntur', 'Tenali', 'tenali', 12
  UNION ALL SELECT 'sri-sathya-sai', 'Hindupur', 'hindupur', 13
  UNION ALL SELECT 'nandyal', 'Srisailam', 'srisailam', 14
  UNION ALL SELECT 'annamayya', 'Madanapalle', 'madanapalle', 15
  UNION ALL SELECT 'west-godavari', 'Tadepalligudem', 'tadepalligudem', 16
  UNION ALL SELECT 'bapatla', 'Chirala', 'chirala', 17
  UNION ALL SELECT 'anjaw', 'Hawai', 'hawai', 0
  UNION ALL SELECT 'bichom', 'Bichom', 'bichom', 0
  UNION ALL SELECT 'changlang', 'Changlang', 'changlang', 0
  UNION ALL SELECT 'dibang-valley', 'Anini', 'anini', 0
  UNION ALL SELECT 'east-kameng', 'Seppa', 'seppa', 0
  UNION ALL SELECT 'east-siang', 'Pasighat', 'pasighat', 0
  UNION ALL SELECT 'kamle', 'Kamle', 'kamle', 0
  UNION ALL SELECT 'keyi-panyor', 'Keyi Panyor', 'keyi-panyor', 0
  UNION ALL SELECT 'kra-daadi', 'Kra Daadi', 'kra-daadi', 0
  UNION ALL SELECT 'kurung-kumey', 'Koloriang', 'koloriang', 0
  UNION ALL SELECT 'lepa-rada', 'Lepa Rada', 'lepa-rada', 0
  UNION ALL SELECT 'lohit', 'Tezu', 'tezu', 0
  UNION ALL SELECT 'longding', 'Longding', 'longding', 0
  UNION ALL SELECT 'lower-dibang-valley', 'Roing', 'roing', 0
  UNION ALL SELECT 'lower-siang', 'Lower Siang', 'lower-siang', 0
  UNION ALL SELECT 'lower-subansiri', 'Ziro', 'ziro', 0
  UNION ALL SELECT 'namsai', 'Namsai', 'namsai', 0
  UNION ALL SELECT 'pakke-kessang', 'Pakke-Kessang', 'pakke-kessang', 0
  UNION ALL SELECT 'papum-pare', 'Itanagar', 'itanagar', 0
  UNION ALL SELECT 'shi-yomi', 'Shi Yomi', 'shi-yomi', 0
  UNION ALL SELECT 'siang', 'Siang', 'siang', 0
  UNION ALL SELECT 'tawang', 'Tawang', 'tawang', 0
  UNION ALL SELECT 'tirap', 'Khonsa', 'khonsa', 0
  UNION ALL SELECT 'upper-siang', 'Yingkiong', 'yingkiong', 0
  UNION ALL SELECT 'upper-subansiri', 'Daporijo', 'daporijo', 0
  UNION ALL SELECT 'west-kameng', 'Bomdila', 'bomdila', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'west-siang' AS dslug, 'Aalo' AS name, 'aalo' AS slug, 0 AS sort_order
  UNION ALL SELECT 'papum-pare', 'Naharlagun', 'naharlagun', 10
  UNION ALL SELECT 'west-kameng', 'Dirang', 'dirang', 11
  UNION ALL SELECT 'shi-yomi', 'Mechuka', 'mechuka', 12
  UNION ALL SELECT 'bajali', 'Pathsala', 'pathsala', 0
  UNION ALL SELECT 'baksa', 'Mushalpur', 'mushalpur', 0
  UNION ALL SELECT 'barpeta', 'Barpeta', 'barpeta', 0
  UNION ALL SELECT 'biswanath', 'Biswanath', 'biswanath', 0
  UNION ALL SELECT 'bongaigaon', 'Bongaigaon', 'bongaigaon', 0
  UNION ALL SELECT 'cachar', 'Silchar', 'silchar', 0
  UNION ALL SELECT 'charaideo', 'Sonari', 'sonari', 0
  UNION ALL SELECT 'chirang', 'Kajalgaon', 'kajalgaon', 0
  UNION ALL SELECT 'darrang', 'Mangaldoi', 'mangaldoi', 0
  UNION ALL SELECT 'dhemaji', 'Dhemaji', 'dhemaji', 0
  UNION ALL SELECT 'dhubri', 'Dhubri', 'dhubri', 0
  UNION ALL SELECT 'dibrugarh', 'Dibrugarh', 'dibrugarh', 0
  UNION ALL SELECT 'dima-hasao', 'Haflong', 'haflong', 0
  UNION ALL SELECT 'goalpara', 'Goalpara', 'goalpara', 0
  UNION ALL SELECT 'golaghat', 'Golaghat', 'golaghat', 0
  UNION ALL SELECT 'hailakandi', 'Hailakandi', 'hailakandi', 0
  UNION ALL SELECT 'hojai', 'Hojai', 'hojai', 0
  UNION ALL SELECT 'jorhat', 'Jorhat', 'jorhat', 0
  UNION ALL SELECT 'kamrup', 'Kamrup', 'kamrup', 0
  UNION ALL SELECT 'kamrup-metropolitan', 'Guwahati', 'guwahati', 0
  UNION ALL SELECT 'karbi-anglong', 'Diphu', 'diphu', 0
  UNION ALL SELECT 'kokrajhar', 'Kokrajhar', 'kokrajhar', 0
  UNION ALL SELECT 'lakhimpur', 'North Lakhimpur', 'north-lakhimpur', 0
  UNION ALL SELECT 'majuli', 'Majuli', 'majuli', 0
  UNION ALL SELECT 'morigaon', 'Morigaon', 'morigaon', 0
  UNION ALL SELECT 'nagaon', 'Nagaon', 'nagaon', 0
  UNION ALL SELECT 'nalbari', 'Nalbari', 'nalbari', 0
  UNION ALL SELECT 'sivasagar', 'Sivasagar', 'sivasagar', 0
  UNION ALL SELECT 'sonitpur', 'Tezpur', 'tezpur', 0
  UNION ALL SELECT 'south-salmara-mankachar', 'Hatsingimari', 'hatsingimari', 0
  UNION ALL SELECT 'sribhumi', 'Sribhumi', 'sribhumi', 0
  UNION ALL SELECT 'tamulpur', 'Tamulpur', 'tamulpur', 0
  UNION ALL SELECT 'tinsukia', 'Tinsukia', 'tinsukia', 0
  UNION ALL SELECT 'udalguri', 'Udalguri', 'udalguri', 0
  UNION ALL SELECT 'west-karbi-anglong', 'Hamren', 'hamren', 0
  UNION ALL SELECT 'kamrup-metropolitan', 'Dispur', 'dispur', 10
  UNION ALL SELECT 'golaghat', 'Kaziranga', 'kaziranga', 11
  UNION ALL SELECT 'tinsukia', 'Digboi', 'digboi', 12
  UNION ALL SELECT 'araria', 'Araria', 'araria', 0
  UNION ALL SELECT 'arwal', 'Arwal', 'arwal', 0
  UNION ALL SELECT 'aurangabad', 'Aurangabad', 'aurangabad', 0
  UNION ALL SELECT 'banka', 'Banka', 'banka', 0
  UNION ALL SELECT 'begusarai', 'Begusarai', 'begusarai', 0
  UNION ALL SELECT 'bhagalpur', 'Bhagalpur', 'bhagalpur', 0
  UNION ALL SELECT 'bhojpur', 'Arrah', 'arrah', 0
  UNION ALL SELECT 'buxar', 'Buxar', 'buxar', 0
  UNION ALL SELECT 'darbhanga', 'Darbhanga', 'darbhanga', 0
  UNION ALL SELECT 'east-champaran', 'Motihari', 'motihari', 0
  UNION ALL SELECT 'gaya', 'Gaya', 'gaya', 0
  UNION ALL SELECT 'gopalganj', 'Gopalganj', 'gopalganj', 0
  UNION ALL SELECT 'jamui', 'Jamui', 'jamui', 0
  UNION ALL SELECT 'jehanabad', 'Jehanabad', 'jehanabad', 0
  UNION ALL SELECT 'kaimur', 'Bhabua', 'bhabua', 0
  UNION ALL SELECT 'katihar', 'Katihar', 'katihar', 0
  UNION ALL SELECT 'khagaria', 'Khagaria', 'khagaria', 0
  UNION ALL SELECT 'kishanganj', 'Kishanganj', 'kishanganj', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'lakhisarai' AS dslug, 'Lakhisarai' AS name, 'lakhisarai' AS slug, 0 AS sort_order
  UNION ALL SELECT 'madhepura', 'Madhepura', 'madhepura', 0
  UNION ALL SELECT 'madhubani', 'Madhubani', 'madhubani', 0
  UNION ALL SELECT 'munger', 'Munger', 'munger', 0
  UNION ALL SELECT 'muzaffarpur', 'Muzaffarpur', 'muzaffarpur', 0
  UNION ALL SELECT 'nalanda', 'Bihar Sharif', 'bihar-sharif', 0
  UNION ALL SELECT 'nawada', 'Nawada', 'nawada', 0
  UNION ALL SELECT 'patna', 'Patna', 'patna', 0
  UNION ALL SELECT 'purnia', 'Purnia', 'purnia', 0
  UNION ALL SELECT 'rohtas', 'Sasaram', 'sasaram', 0
  UNION ALL SELECT 'saharsa', 'Saharsa', 'saharsa', 0
  UNION ALL SELECT 'samastipur', 'Samastipur', 'samastipur', 0
  UNION ALL SELECT 'saran', 'Chhapra', 'chhapra', 0
  UNION ALL SELECT 'sheikhpura', 'Sheikhpura', 'sheikhpura', 0
  UNION ALL SELECT 'sheohar', 'Sheohar', 'sheohar', 0
  UNION ALL SELECT 'sitamarhi', 'Sitamarhi', 'sitamarhi', 0
  UNION ALL SELECT 'siwan', 'Siwan', 'siwan', 0
  UNION ALL SELECT 'supaul', 'Supaul', 'supaul', 0
  UNION ALL SELECT 'vaishali', 'Hajipur', 'hajipur', 0
  UNION ALL SELECT 'west-champaran', 'Bettiah', 'bettiah', 0
  UNION ALL SELECT 'gaya', 'Bodh Gaya', 'bodh-gaya', 10
  UNION ALL SELECT 'nalanda', 'Rajgir', 'rajgir', 11
  UNION ALL SELECT 'rohtas', 'Dehri', 'dehri', 12
  UNION ALL SELECT 'balod', 'Balod', 'balod', 0
  UNION ALL SELECT 'baloda-bazar', 'Baloda Bazar', 'baloda-bazar', 0
  UNION ALL SELECT 'balrampur-ramanujganj', 'Balrampur', 'balrampur-chhattisgarh', 0
  UNION ALL SELECT 'bastar', 'Jagdalpur', 'jagdalpur', 0
  UNION ALL SELECT 'bemetara', 'Bemetara', 'bemetara', 0
  UNION ALL SELECT 'bijapur', 'Bijapur', 'bijapur', 0
  UNION ALL SELECT 'bilaspur-chhattisgarh', 'Bilaspur', 'bilaspur', 0
  UNION ALL SELECT 'dantewada', 'Dantewada', 'dantewada', 0
  UNION ALL SELECT 'dhamtari', 'Dhamtari', 'dhamtari', 0
  UNION ALL SELECT 'durg', 'Durg', 'durg', 0
  UNION ALL SELECT 'gariaband', 'Gariaband', 'gariaband', 0
  UNION ALL SELECT 'gaurela-pendra-marwahi', 'Gaurela', 'gaurela', 0
  UNION ALL SELECT 'janjgir-champa', 'Janjgir', 'janjgir', 0
  UNION ALL SELECT 'jashpur', 'Jashpur', 'jashpur', 0
  UNION ALL SELECT 'kabirdham', 'Kawardha', 'kawardha', 0
  UNION ALL SELECT 'kanker', 'Kanker', 'kanker', 0
  UNION ALL SELECT 'khairagarh-chhuikhadan-gandai', 'Khairagarh', 'khairagarh', 0
  UNION ALL SELECT 'kondagaon', 'Kondagaon', 'kondagaon', 0
  UNION ALL SELECT 'korba', 'Korba', 'korba', 0
  UNION ALL SELECT 'koriya', 'Baikunthpur', 'baikunthpur', 0
  UNION ALL SELECT 'mahasamund', 'Mahasamund', 'mahasamund', 0
  UNION ALL SELECT 'manendragarh-chirmiri-bharatpur', 'Manendragarh', 'manendragarh', 0
  UNION ALL SELECT 'mohla-manpur-ambagarh-chowki', 'Mohla', 'mohla', 0
  UNION ALL SELECT 'mungeli', 'Mungeli', 'mungeli', 0
  UNION ALL SELECT 'narayanpur', 'Narayanpur', 'narayanpur', 0
  UNION ALL SELECT 'raigarh', 'Raigarh', 'raigarh', 0
  UNION ALL SELECT 'raipur', 'Raipur', 'raipur', 0
  UNION ALL SELECT 'rajnandgaon', 'Rajnandgaon', 'rajnandgaon', 0
  UNION ALL SELECT 'sakti', 'Sakti', 'sakti', 0
  UNION ALL SELECT 'sarangarh-bilaigarh', 'Sarangarh', 'sarangarh', 0
  UNION ALL SELECT 'sukma', 'Sukma', 'sukma', 0
  UNION ALL SELECT 'surajpur', 'Surajpur', 'surajpur', 0
  UNION ALL SELECT 'surguja', 'Ambikapur', 'ambikapur', 0
  UNION ALL SELECT 'durg', 'Bhilai', 'bhilai', 10
  UNION ALL SELECT 'raipur', 'Nava Raipur', 'nava-raipur', 11
  UNION ALL SELECT 'manendragarh-chirmiri-bharatpur', 'Chirmiri', 'chirmiri', 12
  UNION ALL SELECT 'north-goa', 'Panaji', 'panaji', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'south-goa' AS dslug, 'Margao' AS name, 'margao' AS slug, 0 AS sort_order
  UNION ALL SELECT 'north-goa', 'Mapusa', 'mapusa', 10
  UNION ALL SELECT 'north-goa', 'Calangute', 'calangute', 11
  UNION ALL SELECT 'north-goa', 'Anjuna', 'anjuna', 12
  UNION ALL SELECT 'north-goa', 'Ponda', 'ponda', 13
  UNION ALL SELECT 'south-goa', 'Vasco da Gama', 'vasco-da-gama', 14
  UNION ALL SELECT 'south-goa', 'Colva', 'colva', 15
  UNION ALL SELECT 'south-goa', 'Palolem', 'palolem', 16
  UNION ALL SELECT 'ahmedabad', 'Ahmedabad', 'ahmedabad', 0
  UNION ALL SELECT 'amreli', 'Amreli', 'amreli', 0
  UNION ALL SELECT 'anand', 'Anand', 'anand', 0
  UNION ALL SELECT 'aravalli', 'Modasa', 'modasa', 0
  UNION ALL SELECT 'banaskantha', 'Palanpur', 'palanpur', 0
  UNION ALL SELECT 'bharuch', 'Bharuch', 'bharuch', 0
  UNION ALL SELECT 'bhavnagar', 'Bhavnagar', 'bhavnagar', 0
  UNION ALL SELECT 'botad', 'Botad', 'botad', 0
  UNION ALL SELECT 'chhota-udaipur', 'Chhota Udaipur', 'chhota-udaipur', 0
  UNION ALL SELECT 'dahod', 'Dahod', 'dahod', 0
  UNION ALL SELECT 'dang', 'Ahwa', 'ahwa', 0
  UNION ALL SELECT 'devbhumi-dwarka', 'Dwarka', 'dwarka', 0
  UNION ALL SELECT 'gandhinagar', 'Gandhinagar', 'gandhinagar', 0
  UNION ALL SELECT 'gir-somnath', 'Veraval', 'veraval', 0
  UNION ALL SELECT 'jamnagar', 'Jamnagar', 'jamnagar', 0
  UNION ALL SELECT 'junagadh', 'Junagadh', 'junagadh', 0
  UNION ALL SELECT 'kheda', 'Nadiad', 'nadiad', 0
  UNION ALL SELECT 'kutch', 'Bhuj', 'bhuj', 0
  UNION ALL SELECT 'mahisagar', 'Lunawada', 'lunawada', 0
  UNION ALL SELECT 'mehsana', 'Mehsana', 'mehsana', 0
  UNION ALL SELECT 'morbi', 'Morbi', 'morbi', 0
  UNION ALL SELECT 'narmada', 'Rajpipla', 'rajpipla', 0
  UNION ALL SELECT 'navsari', 'Navsari', 'navsari', 0
  UNION ALL SELECT 'panchmahal', 'Godhra', 'godhra', 0
  UNION ALL SELECT 'patan', 'Patan', 'patan', 0
  UNION ALL SELECT 'porbandar', 'Porbandar', 'porbandar', 0
  UNION ALL SELECT 'rajkot', 'Rajkot', 'rajkot', 0
  UNION ALL SELECT 'sabarkantha', 'Himmatnagar', 'himmatnagar', 0
  UNION ALL SELECT 'surat', 'Surat', 'surat', 0
  UNION ALL SELECT 'surendranagar', 'Surendranagar', 'surendranagar', 0
  UNION ALL SELECT 'tapi', 'Vyara', 'vyara', 0
  UNION ALL SELECT 'vadodara', 'Vadodara', 'vadodara', 0
  UNION ALL SELECT 'valsad', 'Valsad', 'valsad', 0
  UNION ALL SELECT 'vav-tharad', 'Tharad', 'tharad', 0
  UNION ALL SELECT 'gandhinagar', 'GIFT City', 'gift-city', 10
  UNION ALL SELECT 'kutch', 'Gandhidham', 'gandhidham', 11
  UNION ALL SELECT 'valsad', 'Vapi', 'vapi', 12
  UNION ALL SELECT 'bharuch', 'Ankleshwar', 'ankleshwar', 13
  UNION ALL SELECT 'gir-somnath', 'Somnath', 'somnath', 14
  UNION ALL SELECT 'dang', 'Saputara', 'saputara', 15
  UNION ALL SELECT 'narmada', 'Ekta Nagar', 'ekta-nagar', 16
  UNION ALL SELECT 'ahmedabad', 'Sanand', 'sanand', 17
  UNION ALL SELECT 'ambala', 'Ambala', 'ambala', 0
  UNION ALL SELECT 'bhiwani', 'Bhiwani', 'bhiwani', 0
  UNION ALL SELECT 'charkhi-dadri', 'Charkhi Dadri', 'charkhi-dadri', 0
  UNION ALL SELECT 'faridabad', 'Faridabad', 'faridabad', 0
  UNION ALL SELECT 'fatehabad', 'Fatehabad', 'fatehabad', 0
  UNION ALL SELECT 'gurugram', 'Gurugram', 'gurugram', 0
  UNION ALL SELECT 'hisar', 'Hisar', 'hisar', 0
  UNION ALL SELECT 'jhajjar', 'Jhajjar', 'jhajjar', 0
  UNION ALL SELECT 'jind', 'Jind', 'jind', 0
  UNION ALL SELECT 'kaithal', 'Kaithal', 'kaithal', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'karnal' AS dslug, 'Karnal' AS name, 'karnal' AS slug, 0 AS sort_order
  UNION ALL SELECT 'kurukshetra', 'Kurukshetra', 'kurukshetra', 0
  UNION ALL SELECT 'mahendragarh', 'Narnaul', 'narnaul', 0
  UNION ALL SELECT 'nuh', 'Nuh', 'nuh', 0
  UNION ALL SELECT 'palwal', 'Palwal', 'palwal', 0
  UNION ALL SELECT 'panchkula', 'Panchkula', 'panchkula', 0
  UNION ALL SELECT 'panipat', 'Panipat', 'panipat', 0
  UNION ALL SELECT 'rewari', 'Rewari', 'rewari', 0
  UNION ALL SELECT 'rohtak', 'Rohtak', 'rohtak', 0
  UNION ALL SELECT 'sirsa', 'Sirsa', 'sirsa', 0
  UNION ALL SELECT 'sonipat', 'Sonipat', 'sonipat', 0
  UNION ALL SELECT 'yamunanagar', 'Yamunanagar', 'yamunanagar', 0
  UNION ALL SELECT 'gurugram', 'Manesar', 'manesar', 10
  UNION ALL SELECT 'jhajjar', 'Bahadurgarh', 'bahadurgarh', 11
  UNION ALL SELECT 'rewari', 'Dharuhera', 'dharuhera', 12
  UNION ALL SELECT 'bilaspur-himachal-pradesh', 'Bilaspur', 'bilaspur-himachal-pradesh', 0
  UNION ALL SELECT 'chamba', 'Chamba', 'chamba', 0
  UNION ALL SELECT 'hamirpur-himachal-pradesh', 'Hamirpur', 'hamirpur', 0
  UNION ALL SELECT 'kangra', 'Dharamshala', 'dharamshala', 0
  UNION ALL SELECT 'kinnaur', 'Reckong Peo', 'reckong-peo', 0
  UNION ALL SELECT 'kullu', 'Kullu', 'kullu', 0
  UNION ALL SELECT 'lahaul-and-spiti', 'Keylong', 'keylong', 0
  UNION ALL SELECT 'mandi', 'Mandi', 'mandi', 0
  UNION ALL SELECT 'shimla', 'Shimla', 'shimla', 0
  UNION ALL SELECT 'sirmaur', 'Nahan', 'nahan', 0
  UNION ALL SELECT 'solan', 'Solan', 'solan', 0
  UNION ALL SELECT 'una', 'Una', 'una', 0
  UNION ALL SELECT 'kullu', 'Manali', 'manali', 10
  UNION ALL SELECT 'kullu', 'Kasol', 'kasol', 11
  UNION ALL SELECT 'kangra', 'McLeod Ganj', 'mcleod-ganj', 12
  UNION ALL SELECT 'kangra', 'Palampur', 'palampur', 13
  UNION ALL SELECT 'kangra', 'Bir', 'bir', 14
  UNION ALL SELECT 'solan', 'Kasauli', 'kasauli', 15
  UNION ALL SELECT 'chamba', 'Dalhousie', 'dalhousie', 16
  UNION ALL SELECT 'lahaul-and-spiti', 'Kaza', 'kaza', 17
  UNION ALL SELECT 'solan', 'Baddi', 'baddi', 18
  UNION ALL SELECT 'bokaro', 'Bokaro Steel City', 'bokaro-steel-city', 0
  UNION ALL SELECT 'chatra', 'Chatra', 'chatra', 0
  UNION ALL SELECT 'deoghar', 'Deoghar', 'deoghar', 0
  UNION ALL SELECT 'dhanbad', 'Dhanbad', 'dhanbad', 0
  UNION ALL SELECT 'dumka', 'Dumka', 'dumka', 0
  UNION ALL SELECT 'east-singhbhum', 'Jamshedpur', 'jamshedpur', 0
  UNION ALL SELECT 'garhwa', 'Garhwa', 'garhwa', 0
  UNION ALL SELECT 'giridih', 'Giridih', 'giridih', 0
  UNION ALL SELECT 'godda', 'Godda', 'godda', 0
  UNION ALL SELECT 'gumla', 'Gumla', 'gumla', 0
  UNION ALL SELECT 'hazaribagh', 'Hazaribagh', 'hazaribagh', 0
  UNION ALL SELECT 'jamtara', 'Jamtara', 'jamtara', 0
  UNION ALL SELECT 'khunti', 'Khunti', 'khunti', 0
  UNION ALL SELECT 'koderma', 'Koderma', 'koderma', 0
  UNION ALL SELECT 'latehar', 'Latehar', 'latehar', 0
  UNION ALL SELECT 'lohardaga', 'Lohardaga', 'lohardaga', 0
  UNION ALL SELECT 'pakur', 'Pakur', 'pakur', 0
  UNION ALL SELECT 'palamu', 'Medininagar', 'medininagar', 0
  UNION ALL SELECT 'ramgarh', 'Ramgarh', 'ramgarh', 0
  UNION ALL SELECT 'ranchi', 'Ranchi', 'ranchi', 0
  UNION ALL SELECT 'sahibganj', 'Sahibganj', 'sahibganj', 0
  UNION ALL SELECT 'seraikela-kharsawan', 'Seraikela', 'seraikela', 0
  UNION ALL SELECT 'simdega', 'Simdega', 'simdega', 0
  UNION ALL SELECT 'west-singhbhum', 'Chaibasa', 'chaibasa', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'latehar' AS dslug, 'Netarhat' AS name, 'netarhat' AS slug, 10 AS sort_order
  UNION ALL SELECT 'seraikela-kharsawan', 'Adityapur', 'adityapur', 11
  UNION ALL SELECT 'bagalkot', 'Bagalkot', 'bagalkot', 0
  UNION ALL SELECT 'ballari', 'Ballari', 'ballari', 0
  UNION ALL SELECT 'belagavi', 'Belagavi', 'belagavi', 0
  UNION ALL SELECT 'bengaluru-rural', 'Bengaluru Rural', 'bengaluru-rural', 0
  UNION ALL SELECT 'bengaluru-south', 'Ramanagara', 'ramanagara', 0
  UNION ALL SELECT 'bengaluru-urban', 'Bengaluru', 'bengaluru', 0
  UNION ALL SELECT 'bidar', 'Bidar', 'bidar', 0
  UNION ALL SELECT 'chamarajanagar', 'Chamarajanagar', 'chamarajanagar', 0
  UNION ALL SELECT 'chikkaballapur', 'Chikkaballapur', 'chikkaballapur', 0
  UNION ALL SELECT 'chikkamagaluru', 'Chikkamagaluru', 'chikkamagaluru', 0
  UNION ALL SELECT 'chitradurga', 'Chitradurga', 'chitradurga', 0
  UNION ALL SELECT 'dakshina-kannada', 'Mangaluru', 'mangaluru', 0
  UNION ALL SELECT 'davanagere', 'Davanagere', 'davanagere', 0
  UNION ALL SELECT 'dharwad', 'Dharwad', 'dharwad', 0
  UNION ALL SELECT 'gadag', 'Gadag', 'gadag', 0
  UNION ALL SELECT 'hassan', 'Hassan', 'hassan', 0
  UNION ALL SELECT 'haveri', 'Haveri', 'haveri', 0
  UNION ALL SELECT 'kalaburagi', 'Kalaburagi', 'kalaburagi', 0
  UNION ALL SELECT 'kodagu', 'Madikeri', 'madikeri', 0
  UNION ALL SELECT 'kolar', 'Kolar', 'kolar', 0
  UNION ALL SELECT 'koppal', 'Koppal', 'koppal', 0
  UNION ALL SELECT 'mandya', 'Mandya', 'mandya', 0
  UNION ALL SELECT 'mysuru', 'Mysuru', 'mysuru', 0
  UNION ALL SELECT 'raichur', 'Raichur', 'raichur', 0
  UNION ALL SELECT 'shivamogga', 'Shivamogga', 'shivamogga', 0
  UNION ALL SELECT 'tumakuru', 'Tumakuru', 'tumakuru', 0
  UNION ALL SELECT 'udupi', 'Udupi', 'udupi', 0
  UNION ALL SELECT 'uttara-kannada', 'Karwar', 'karwar', 0
  UNION ALL SELECT 'vijayanagara', 'Hosapete', 'hosapete', 0
  UNION ALL SELECT 'vijayapura', 'Vijayapura', 'vijayapura', 0
  UNION ALL SELECT 'yadgir', 'Yadgir', 'yadgir', 0
  UNION ALL SELECT 'dharwad', 'Hubballi', 'hubballi', 10
  UNION ALL SELECT 'vijayanagara', 'Hampi', 'hampi', 11
  UNION ALL SELECT 'udupi', 'Manipal', 'manipal', 12
  UNION ALL SELECT 'uttara-kannada', 'Gokarna', 'gokarna', 13
  UNION ALL SELECT 'chikkaballapur', 'Nandi Hills', 'nandi-hills', 14
  UNION ALL SELECT 'mandya', 'Srirangapatna', 'srirangapatna', 15
  UNION ALL SELECT 'kodagu', 'Coorg', 'coorg', 16
  UNION ALL SELECT 'bengaluru-rural', 'Hosakote', 'hosakote', 17
  UNION ALL SELECT 'bengaluru-rural', 'Devanahalli', 'devanahalli', 18
  UNION ALL SELECT 'bengaluru-rural', 'Doddaballapura', 'doddaballapura', 19
  UNION ALL SELECT 'bengaluru-urban', 'Anekal', 'anekal', 20
  UNION ALL SELECT 'bagalkot', 'Badami', 'badami', 21
  UNION ALL SELECT 'alappuzha', 'Alappuzha', 'alappuzha', 0
  UNION ALL SELECT 'ernakulam', 'Kochi', 'kochi', 0
  UNION ALL SELECT 'idukki', 'Idukki', 'idukki', 0
  UNION ALL SELECT 'kannur', 'Kannur', 'kannur', 0
  UNION ALL SELECT 'kasaragod', 'Kasaragod', 'kasaragod', 0
  UNION ALL SELECT 'kollam', 'Kollam', 'kollam', 0
  UNION ALL SELECT 'kottayam', 'Kottayam', 'kottayam', 0
  UNION ALL SELECT 'kozhikode', 'Kozhikode', 'kozhikode', 0
  UNION ALL SELECT 'malappuram', 'Malappuram', 'malappuram', 0
  UNION ALL SELECT 'palakkad', 'Palakkad', 'palakkad', 0
  UNION ALL SELECT 'pathanamthitta', 'Pathanamthitta', 'pathanamthitta', 0
  UNION ALL SELECT 'thiruvananthapuram', 'Thiruvananthapuram', 'thiruvananthapuram', 0
  UNION ALL SELECT 'thrissur', 'Thrissur', 'thrissur', 0
  UNION ALL SELECT 'wayanad', 'Kalpetta', 'kalpetta', 0
  UNION ALL SELECT 'idukki', 'Munnar', 'munnar', 10
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'idukki' AS dslug, 'Thekkady' AS name, 'thekkady' AS slug, 11 AS sort_order
  UNION ALL SELECT 'thiruvananthapuram', 'Varkala', 'varkala', 12
  UNION ALL SELECT 'thiruvananthapuram', 'Kovalam', 'kovalam', 13
  UNION ALL SELECT 'thrissur', 'Guruvayur', 'guruvayur', 14
  UNION ALL SELECT 'kottayam', 'Kumarakom', 'kumarakom', 15
  UNION ALL SELECT 'ernakulam', 'Aluva', 'aluva', 16
  UNION ALL SELECT 'ernakulam', 'Kakkanad', 'kakkanad', 17
  UNION ALL SELECT 'ernakulam', 'Fort Kochi', 'fort-kochi', 18
  UNION ALL SELECT 'kannur', 'Thalassery', 'thalassery', 19
  UNION ALL SELECT 'malappuram', 'Manjeri', 'manjeri', 20
  UNION ALL SELECT 'malappuram', 'Tirur', 'tirur', 21
  UNION ALL SELECT 'pathanamthitta', 'Sabarimala', 'sabarimala', 22
  UNION ALL SELECT 'thiruvananthapuram', 'Technopark', 'technopark', 23
  UNION ALL SELECT 'agar-malwa', 'Agar', 'agar', 0
  UNION ALL SELECT 'alirajpur', 'Alirajpur', 'alirajpur', 0
  UNION ALL SELECT 'anuppur', 'Anuppur', 'anuppur', 0
  UNION ALL SELECT 'ashoknagar', 'Ashoknagar', 'ashoknagar', 0
  UNION ALL SELECT 'balaghat', 'Balaghat', 'balaghat', 0
  UNION ALL SELECT 'barwani', 'Barwani', 'barwani', 0
  UNION ALL SELECT 'betul', 'Betul', 'betul', 0
  UNION ALL SELECT 'bhind', 'Bhind', 'bhind', 0
  UNION ALL SELECT 'bhopal', 'Bhopal', 'bhopal', 0
  UNION ALL SELECT 'burhanpur', 'Burhanpur', 'burhanpur', 0
  UNION ALL SELECT 'chhatarpur', 'Chhatarpur', 'chhatarpur', 0
  UNION ALL SELECT 'chhindwara', 'Chhindwara', 'chhindwara', 0
  UNION ALL SELECT 'damoh', 'Damoh', 'damoh', 0
  UNION ALL SELECT 'datia', 'Datia', 'datia', 0
  UNION ALL SELECT 'dewas', 'Dewas', 'dewas', 0
  UNION ALL SELECT 'dhar', 'Dhar', 'dhar', 0
  UNION ALL SELECT 'dindori', 'Dindori', 'dindori', 0
  UNION ALL SELECT 'guna', 'Guna', 'guna', 0
  UNION ALL SELECT 'gwalior', 'Gwalior', 'gwalior', 0
  UNION ALL SELECT 'harda', 'Harda', 'harda', 0
  UNION ALL SELECT 'indore', 'Indore', 'indore', 0
  UNION ALL SELECT 'jabalpur', 'Jabalpur', 'jabalpur', 0
  UNION ALL SELECT 'jhabua', 'Jhabua', 'jhabua', 0
  UNION ALL SELECT 'katni', 'Katni', 'katni', 0
  UNION ALL SELECT 'khandwa', 'Khandwa', 'khandwa', 0
  UNION ALL SELECT 'khargone', 'Khargone', 'khargone', 0
  UNION ALL SELECT 'maihar', 'Maihar', 'maihar', 0
  UNION ALL SELECT 'mandla', 'Mandla', 'mandla', 0
  UNION ALL SELECT 'mandsaur', 'Mandsaur', 'mandsaur', 0
  UNION ALL SELECT 'mauganj', 'Mauganj', 'mauganj', 0
  UNION ALL SELECT 'morena', 'Morena', 'morena', 0
  UNION ALL SELECT 'narmadapuram', 'Narmadapuram', 'narmadapuram', 0
  UNION ALL SELECT 'narsinghpur', 'Narsinghpur', 'narsinghpur', 0
  UNION ALL SELECT 'neemuch', 'Neemuch', 'neemuch', 0
  UNION ALL SELECT 'niwari', 'Niwari', 'niwari', 0
  UNION ALL SELECT 'pandhurna', 'Pandhurna', 'pandhurna', 0
  UNION ALL SELECT 'panna', 'Panna', 'panna', 0
  UNION ALL SELECT 'raisen', 'Raisen', 'raisen', 0
  UNION ALL SELECT 'rajgarh', 'Rajgarh', 'rajgarh', 0
  UNION ALL SELECT 'ratlam', 'Ratlam', 'ratlam', 0
  UNION ALL SELECT 'rewa', 'Rewa', 'rewa', 0
  UNION ALL SELECT 'sagar', 'Sagar', 'sagar', 0
  UNION ALL SELECT 'satna', 'Satna', 'satna', 0
  UNION ALL SELECT 'sehore', 'Sehore', 'sehore', 0
  UNION ALL SELECT 'seoni', 'Seoni', 'seoni', 0
  UNION ALL SELECT 'shahdol', 'Shahdol', 'shahdol', 0
  UNION ALL SELECT 'shajapur', 'Shajapur', 'shajapur', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'sheopur' AS dslug, 'Sheopur' AS name, 'sheopur' AS slug, 0 AS sort_order
  UNION ALL SELECT 'shivpuri', 'Shivpuri', 'shivpuri', 0
  UNION ALL SELECT 'sidhi', 'Sidhi', 'sidhi', 0
  UNION ALL SELECT 'singrauli', 'Singrauli', 'singrauli', 0
  UNION ALL SELECT 'tikamgarh', 'Tikamgarh', 'tikamgarh', 0
  UNION ALL SELECT 'ujjain', 'Ujjain', 'ujjain', 0
  UNION ALL SELECT 'umaria', 'Umaria', 'umaria', 0
  UNION ALL SELECT 'vidisha', 'Vidisha', 'vidisha', 0
  UNION ALL SELECT 'indore', 'Mhow', 'mhow', 10
  UNION ALL SELECT 'narmadapuram', 'Pachmarhi', 'pachmarhi', 11
  UNION ALL SELECT 'narmadapuram', 'Itarsi', 'itarsi', 12
  UNION ALL SELECT 'chhatarpur', 'Khajuraho', 'khajuraho', 13
  UNION ALL SELECT 'niwari', 'Orchha', 'orchha', 14
  UNION ALL SELECT 'khandwa', 'Omkareshwar', 'omkareshwar', 15
  UNION ALL SELECT 'khargone', 'Maheshwar', 'maheshwar', 16
  UNION ALL SELECT 'dhar', 'Mandu', 'mandu', 17
  UNION ALL SELECT 'raisen', 'Sanchi', 'sanchi', 18
  UNION ALL SELECT 'umaria', 'Bandhavgarh', 'bandhavgarh', 19
  UNION ALL SELECT 'ahilyanagar', 'Ahilyanagar', 'ahilyanagar', 0
  UNION ALL SELECT 'akola', 'Akola', 'akola', 0
  UNION ALL SELECT 'amravati', 'Amravati', 'amravati', 0
  UNION ALL SELECT 'beed', 'Beed', 'beed', 0
  UNION ALL SELECT 'bhandara', 'Bhandara', 'bhandara', 0
  UNION ALL SELECT 'buldhana', 'Buldhana', 'buldhana', 0
  UNION ALL SELECT 'chandrapur', 'Chandrapur', 'chandrapur', 0
  UNION ALL SELECT 'chhatrapati-sambhajinagar', 'Chhatrapati Sambhajinagar', 'chhatrapati-sambhajinagar', 0
  UNION ALL SELECT 'dharashiv', 'Dharashiv', 'dharashiv', 0
  UNION ALL SELECT 'dhule', 'Dhule', 'dhule', 0
  UNION ALL SELECT 'gadchiroli', 'Gadchiroli', 'gadchiroli', 0
  UNION ALL SELECT 'gondia', 'Gondia', 'gondia', 0
  UNION ALL SELECT 'hingoli', 'Hingoli', 'hingoli', 0
  UNION ALL SELECT 'jalgaon', 'Jalgaon', 'jalgaon', 0
  UNION ALL SELECT 'jalna', 'Jalna', 'jalna', 0
  UNION ALL SELECT 'kolhapur', 'Kolhapur', 'kolhapur', 0
  UNION ALL SELECT 'latur', 'Latur', 'latur', 0
  UNION ALL SELECT 'mumbai-city', 'Mumbai', 'mumbai', 0
  UNION ALL SELECT 'mumbai-suburban', 'Mumbai Suburban', 'mumbai-suburban', 0
  UNION ALL SELECT 'nagpur', 'Nagpur', 'nagpur', 0
  UNION ALL SELECT 'nanded', 'Nanded', 'nanded', 0
  UNION ALL SELECT 'nandurbar', 'Nandurbar', 'nandurbar', 0
  UNION ALL SELECT 'nashik', 'Nashik', 'nashik', 0
  UNION ALL SELECT 'palghar', 'Palghar', 'palghar', 0
  UNION ALL SELECT 'parbhani', 'Parbhani', 'parbhani', 0
  UNION ALL SELECT 'pune', 'Pune', 'pune', 0
  UNION ALL SELECT 'raigad', 'Alibag', 'alibag', 0
  UNION ALL SELECT 'ratnagiri', 'Ratnagiri', 'ratnagiri', 0
  UNION ALL SELECT 'sangli', 'Sangli', 'sangli', 0
  UNION ALL SELECT 'satara', 'Satara', 'satara', 0
  UNION ALL SELECT 'sindhudurg', 'Oros', 'oros', 0
  UNION ALL SELECT 'solapur', 'Solapur', 'solapur', 0
  UNION ALL SELECT 'thane', 'Thane', 'thane', 0
  UNION ALL SELECT 'wardha', 'Wardha', 'wardha', 0
  UNION ALL SELECT 'washim', 'Washim', 'washim', 0
  UNION ALL SELECT 'yavatmal', 'Yavatmal', 'yavatmal', 0
  UNION ALL SELECT 'thane', 'Navi Mumbai', 'navi-mumbai', 10
  UNION ALL SELECT 'thane', 'Kalyan', 'kalyan', 11
  UNION ALL SELECT 'thane', 'Dombivli', 'dombivli', 12
  UNION ALL SELECT 'thane', 'Bhiwandi', 'bhiwandi', 13
  UNION ALL SELECT 'thane', 'Mira-Bhayandar', 'mira-bhayandar', 14
  UNION ALL SELECT 'palghar', 'Vasai-Virar', 'vasai-virar', 15
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'pune' AS dslug, 'Pimpri-Chinchwad' AS name, 'pimpri-chinchwad' AS slug, 16 AS sort_order
  UNION ALL SELECT 'pune', 'Lonavala', 'lonavala', 17
  UNION ALL SELECT 'pune', 'Hinjewadi', 'hinjewadi', 18
  UNION ALL SELECT 'satara', 'Mahabaleshwar', 'mahabaleshwar', 19
  UNION ALL SELECT 'satara', 'Panchgani', 'panchgani', 20
  UNION ALL SELECT 'ahilyanagar', 'Shirdi', 'shirdi', 21
  UNION ALL SELECT 'raigad', 'Panvel', 'panvel', 22
  UNION ALL SELECT 'raigad', 'Kharghar', 'kharghar', 23
  UNION ALL SELECT 'kolhapur', 'Ichalkaranji', 'ichalkaranji', 24
  UNION ALL SELECT 'nashik', 'Malegaon', 'malegaon', 25
  UNION ALL SELECT 'chhatrapati-sambhajinagar', 'Aurangabad', 'aurangabad-maharashtra', 26
  UNION ALL SELECT 'mumbai-suburban', 'Andheri', 'andheri', 27
  UNION ALL SELECT 'mumbai-suburban', 'Bandra', 'bandra', 28
  UNION ALL SELECT 'mumbai-suburban', 'Powai', 'powai', 29
  UNION ALL SELECT 'sindhudurg', 'Malvan', 'malvan', 30
  UNION ALL SELECT 'ratnagiri', 'Ganpatipule', 'ganpatipule', 31
  UNION ALL SELECT 'bishnupur', 'Bishnupur', 'bishnupur', 0
  UNION ALL SELECT 'chandel', 'Chandel', 'chandel', 0
  UNION ALL SELECT 'churachandpur', 'Churachandpur', 'churachandpur', 0
  UNION ALL SELECT 'imphal-east', 'Porompat', 'porompat', 0
  UNION ALL SELECT 'imphal-west', 'Imphal', 'imphal', 0
  UNION ALL SELECT 'jiribam', 'Jiribam', 'jiribam', 0
  UNION ALL SELECT 'kakching', 'Kakching', 'kakching', 0
  UNION ALL SELECT 'kamjong', 'Kamjong', 'kamjong', 0
  UNION ALL SELECT 'kangpokpi', 'Kangpokpi', 'kangpokpi', 0
  UNION ALL SELECT 'noney', 'Noney', 'noney', 0
  UNION ALL SELECT 'pherzawl', 'Pherzawl', 'pherzawl', 0
  UNION ALL SELECT 'senapati', 'Senapati', 'senapati', 0
  UNION ALL SELECT 'tamenglong', 'Tamenglong', 'tamenglong', 0
  UNION ALL SELECT 'tengnoupal', 'Tengnoupal', 'tengnoupal', 0
  UNION ALL SELECT 'thoubal', 'Thoubal', 'thoubal', 0
  UNION ALL SELECT 'ukhrul', 'Ukhrul', 'ukhrul', 0
  UNION ALL SELECT 'bishnupur', 'Moirang', 'moirang', 10
  UNION ALL SELECT 'tengnoupal', 'Moreh', 'moreh', 11
  UNION ALL SELECT 'east-garo-hills', 'Williamnagar', 'williamnagar', 0
  UNION ALL SELECT 'east-jaintia-hills', 'Khliehriat', 'khliehriat', 0
  UNION ALL SELECT 'east-khasi-hills', 'Shillong', 'shillong', 0
  UNION ALL SELECT 'eastern-west-khasi-hills', 'Mairang', 'mairang', 0
  UNION ALL SELECT 'north-garo-hills', 'Resubelpara', 'resubelpara', 0
  UNION ALL SELECT 'ri-bhoi', 'Nongpoh', 'nongpoh', 0
  UNION ALL SELECT 'south-garo-hills', 'Baghmara', 'baghmara', 0
  UNION ALL SELECT 'south-west-garo-hills', 'Ampati', 'ampati', 0
  UNION ALL SELECT 'south-west-khasi-hills', 'Mawkyrwat', 'mawkyrwat', 0
  UNION ALL SELECT 'west-garo-hills', 'Tura', 'tura', 0
  UNION ALL SELECT 'west-jaintia-hills', 'Jowai', 'jowai', 0
  UNION ALL SELECT 'west-khasi-hills', 'Nongstoin', 'nongstoin', 0
  UNION ALL SELECT 'east-khasi-hills', 'Sohra', 'sohra', 10
  UNION ALL SELECT 'east-khasi-hills', 'Mawlynnong', 'mawlynnong', 11
  UNION ALL SELECT 'west-jaintia-hills', 'Dawki', 'dawki', 12
  UNION ALL SELECT 'aizawl', 'Aizawl', 'aizawl', 0
  UNION ALL SELECT 'champhai', 'Champhai', 'champhai', 0
  UNION ALL SELECT 'hnahthial', 'Hnahthial', 'hnahthial', 0
  UNION ALL SELECT 'khawzawl', 'Khawzawl', 'khawzawl', 0
  UNION ALL SELECT 'kolasib', 'Kolasib', 'kolasib', 0
  UNION ALL SELECT 'lawngtlai', 'Lawngtlai', 'lawngtlai', 0
  UNION ALL SELECT 'lunglei', 'Lunglei', 'lunglei', 0
  UNION ALL SELECT 'mamit', 'Mamit', 'mamit', 0
  UNION ALL SELECT 'saitual', 'Saitual', 'saitual', 0
  UNION ALL SELECT 'serchhip', 'Serchhip', 'serchhip', 0
  UNION ALL SELECT 'siaha', 'Siaha', 'siaha', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'chumoukedima' AS dslug, 'Chumoukedima' AS name, 'chumoukedima' AS slug, 0 AS sort_order
  UNION ALL SELECT 'dimapur', 'Dimapur', 'dimapur', 0
  UNION ALL SELECT 'kiphire', 'Kiphire', 'kiphire', 0
  UNION ALL SELECT 'kohima', 'Kohima', 'kohima', 0
  UNION ALL SELECT 'longleng', 'Longleng', 'longleng', 0
  UNION ALL SELECT 'meluri', 'Meluri', 'meluri', 0
  UNION ALL SELECT 'mokokchung', 'Mokokchung', 'mokokchung', 0
  UNION ALL SELECT 'mon', 'Mon', 'mon', 0
  UNION ALL SELECT 'niuland', 'Niuland', 'niuland', 0
  UNION ALL SELECT 'noklak', 'Noklak', 'noklak', 0
  UNION ALL SELECT 'peren', 'Peren', 'peren', 0
  UNION ALL SELECT 'phek', 'Phek', 'phek', 0
  UNION ALL SELECT 'shamator', 'Shamator', 'shamator', 0
  UNION ALL SELECT 'tseminyu', 'Tseminyu', 'tseminyu', 0
  UNION ALL SELECT 'tuensang', 'Tuensang', 'tuensang', 0
  UNION ALL SELECT 'wokha', 'Wokha', 'wokha', 0
  UNION ALL SELECT 'zunheboto', 'Zunheboto', 'zunheboto', 0
  UNION ALL SELECT 'kohima', 'Kisama', 'kisama', 10
  UNION ALL SELECT 'kohima', 'Dzukou Valley', 'dzukou-valley', 11
  UNION ALL SELECT 'angul', 'Angul', 'angul', 0
  UNION ALL SELECT 'balangir', 'Balangir', 'balangir', 0
  UNION ALL SELECT 'balasore', 'Balasore', 'balasore', 0
  UNION ALL SELECT 'bargarh', 'Bargarh', 'bargarh', 0
  UNION ALL SELECT 'bhadrak', 'Bhadrak', 'bhadrak', 0
  UNION ALL SELECT 'boudh', 'Boudh', 'boudh', 0
  UNION ALL SELECT 'cuttack', 'Cuttack', 'cuttack', 0
  UNION ALL SELECT 'deogarh', 'Deogarh', 'deogarh', 0
  UNION ALL SELECT 'dhenkanal', 'Dhenkanal', 'dhenkanal', 0
  UNION ALL SELECT 'gajapati', 'Paralakhemundi', 'paralakhemundi', 0
  UNION ALL SELECT 'ganjam', 'Berhampur', 'berhampur', 0
  UNION ALL SELECT 'jagatsinghpur', 'Jagatsinghpur', 'jagatsinghpur', 0
  UNION ALL SELECT 'jajpur', 'Jajpur', 'jajpur', 0
  UNION ALL SELECT 'jharsuguda', 'Jharsuguda', 'jharsuguda', 0
  UNION ALL SELECT 'kalahandi', 'Bhawanipatna', 'bhawanipatna', 0
  UNION ALL SELECT 'kandhamal', 'Phulbani', 'phulbani', 0
  UNION ALL SELECT 'kendrapara', 'Kendrapara', 'kendrapara', 0
  UNION ALL SELECT 'kendujhar', 'Keonjhar', 'keonjhar', 0
  UNION ALL SELECT 'khordha', 'Bhubaneswar', 'bhubaneswar', 0
  UNION ALL SELECT 'koraput', 'Koraput', 'koraput', 0
  UNION ALL SELECT 'malkangiri', 'Malkangiri', 'malkangiri', 0
  UNION ALL SELECT 'mayurbhanj', 'Baripada', 'baripada', 0
  UNION ALL SELECT 'nabarangpur', 'Nabarangpur', 'nabarangpur', 0
  UNION ALL SELECT 'nayagarh', 'Nayagarh', 'nayagarh', 0
  UNION ALL SELECT 'nuapada', 'Nuapada', 'nuapada', 0
  UNION ALL SELECT 'puri', 'Puri', 'puri', 0
  UNION ALL SELECT 'rayagada', 'Rayagada', 'rayagada', 0
  UNION ALL SELECT 'sambalpur', 'Sambalpur', 'sambalpur', 0
  UNION ALL SELECT 'subarnapur', 'Sonepur', 'sonepur', 0
  UNION ALL SELECT 'sundargarh', 'Rourkela', 'rourkela', 0
  UNION ALL SELECT 'puri', 'Konark', 'konark', 10
  UNION ALL SELECT 'ganjam', 'Gopalpur', 'gopalpur', 11
  UNION ALL SELECT 'jagatsinghpur', 'Paradip', 'paradip', 12
  UNION ALL SELECT 'khordha', 'Khordha', 'khordha', 13
  UNION ALL SELECT 'sundargarh', 'Sundargarh', 'sundargarh', 14
  UNION ALL SELECT 'balasore', 'Chandipur', 'chandipur', 15
  UNION ALL SELECT 'angul', 'Talcher', 'talcher', 16
  UNION ALL SELECT 'amritsar', 'Amritsar', 'amritsar', 0
  UNION ALL SELECT 'barnala', 'Barnala', 'barnala', 0
  UNION ALL SELECT 'bathinda', 'Bathinda', 'bathinda', 0
  UNION ALL SELECT 'faridkot', 'Faridkot', 'faridkot', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'fatehgarh-sahib' AS dslug, 'Fatehgarh Sahib' AS name, 'fatehgarh-sahib' AS slug, 0 AS sort_order
  UNION ALL SELECT 'fazilka', 'Fazilka', 'fazilka', 0
  UNION ALL SELECT 'ferozepur', 'Ferozepur', 'ferozepur', 0
  UNION ALL SELECT 'gurdaspur', 'Gurdaspur', 'gurdaspur', 0
  UNION ALL SELECT 'hoshiarpur', 'Hoshiarpur', 'hoshiarpur', 0
  UNION ALL SELECT 'jalandhar', 'Jalandhar', 'jalandhar', 0
  UNION ALL SELECT 'kapurthala', 'Kapurthala', 'kapurthala', 0
  UNION ALL SELECT 'ludhiana', 'Ludhiana', 'ludhiana', 0
  UNION ALL SELECT 'malerkotla', 'Malerkotla', 'malerkotla', 0
  UNION ALL SELECT 'mansa', 'Mansa', 'mansa', 0
  UNION ALL SELECT 'moga', 'Moga', 'moga', 0
  UNION ALL SELECT 'pathankot', 'Pathankot', 'pathankot', 0
  UNION ALL SELECT 'patiala', 'Patiala', 'patiala', 0
  UNION ALL SELECT 'rupnagar', 'Rupnagar', 'rupnagar', 0
  UNION ALL SELECT 'sahibzada-ajit-singh-nagar', 'Mohali', 'mohali', 0
  UNION ALL SELECT 'sangrur', 'Sangrur', 'sangrur', 0
  UNION ALL SELECT 'shaheed-bhagat-singh-nagar', 'Nawanshahr', 'nawanshahr', 0
  UNION ALL SELECT 'sri-muktsar-sahib', 'Muktsar', 'muktsar', 0
  UNION ALL SELECT 'tarn-taran', 'Tarn Taran', 'tarn-taran', 0
  UNION ALL SELECT 'sahibzada-ajit-singh-nagar', 'Zirakpur', 'zirakpur', 10
  UNION ALL SELECT 'sahibzada-ajit-singh-nagar', 'Kharar', 'kharar', 11
  UNION ALL SELECT 'patiala', 'Rajpura', 'rajpura', 12
  UNION ALL SELECT 'ludhiana', 'Khanna', 'khanna', 13
  UNION ALL SELECT 'kapurthala', 'Phagwara', 'phagwara', 14
  UNION ALL SELECT 'rupnagar', 'Anandpur Sahib', 'anandpur-sahib', 15
  UNION ALL SELECT 'gurdaspur', 'Batala', 'batala', 16
  UNION ALL SELECT 'ajmer', 'Ajmer', 'ajmer', 0
  UNION ALL SELECT 'alwar', 'Alwar', 'alwar', 0
  UNION ALL SELECT 'balotra', 'Balotra', 'balotra', 0
  UNION ALL SELECT 'banswara', 'Banswara', 'banswara', 0
  UNION ALL SELECT 'baran', 'Baran', 'baran', 0
  UNION ALL SELECT 'barmer', 'Barmer', 'barmer', 0
  UNION ALL SELECT 'beawar', 'Beawar', 'beawar', 0
  UNION ALL SELECT 'bharatpur', 'Bharatpur', 'bharatpur', 0
  UNION ALL SELECT 'bhilwara', 'Bhilwara', 'bhilwara', 0
  UNION ALL SELECT 'bikaner', 'Bikaner', 'bikaner', 0
  UNION ALL SELECT 'bundi', 'Bundi', 'bundi', 0
  UNION ALL SELECT 'chittorgarh', 'Chittorgarh', 'chittorgarh', 0
  UNION ALL SELECT 'churu', 'Churu', 'churu', 0
  UNION ALL SELECT 'dausa', 'Dausa', 'dausa', 0
  UNION ALL SELECT 'deeg', 'Deeg', 'deeg', 0
  UNION ALL SELECT 'dholpur', 'Dholpur', 'dholpur', 0
  UNION ALL SELECT 'didwana-kuchaman', 'Didwana', 'didwana', 0
  UNION ALL SELECT 'dungarpur', 'Dungarpur', 'dungarpur', 0
  UNION ALL SELECT 'hanumangarh', 'Hanumangarh', 'hanumangarh', 0
  UNION ALL SELECT 'jaipur', 'Jaipur', 'jaipur', 0
  UNION ALL SELECT 'jaisalmer', 'Jaisalmer', 'jaisalmer', 0
  UNION ALL SELECT 'jalore', 'Jalore', 'jalore', 0
  UNION ALL SELECT 'jhalawar', 'Jhalawar', 'jhalawar', 0
  UNION ALL SELECT 'jhunjhunu', 'Jhunjhunu', 'jhunjhunu', 0
  UNION ALL SELECT 'jodhpur', 'Jodhpur', 'jodhpur', 0
  UNION ALL SELECT 'karauli', 'Karauli', 'karauli', 0
  UNION ALL SELECT 'khairthal-tijara', 'Khairthal', 'khairthal', 0
  UNION ALL SELECT 'kota', 'Kota', 'kota', 0
  UNION ALL SELECT 'kotputli-behror', 'Kotputli', 'kotputli', 0
  UNION ALL SELECT 'nagaur', 'Nagaur', 'nagaur', 0
  UNION ALL SELECT 'pali', 'Pali', 'pali', 0
  UNION ALL SELECT 'phalodi', 'Phalodi', 'phalodi', 0
  UNION ALL SELECT 'pratapgarh-rajasthan', 'Pratapgarh', 'pratapgarh', 0
  UNION ALL SELECT 'rajsamand', 'Rajsamand', 'rajsamand', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'salumbar' AS dslug, 'Salumbar' AS name, 'salumbar' AS slug, 0 AS sort_order
  UNION ALL SELECT 'sawai-madhopur', 'Sawai Madhopur', 'sawai-madhopur', 0
  UNION ALL SELECT 'sikar', 'Sikar', 'sikar', 0
  UNION ALL SELECT 'sirohi', 'Sirohi', 'sirohi', 0
  UNION ALL SELECT 'sri-ganganagar', 'Sri Ganganagar', 'sri-ganganagar', 0
  UNION ALL SELECT 'tonk', 'Tonk', 'tonk', 0
  UNION ALL SELECT 'udaipur', 'Udaipur', 'udaipur', 0
  UNION ALL SELECT 'sirohi', 'Mount Abu', 'mount-abu', 10
  UNION ALL SELECT 'ajmer', 'Pushkar', 'pushkar', 11
  UNION ALL SELECT 'ajmer', 'Kishangarh', 'kishangarh', 12
  UNION ALL SELECT 'khairthal-tijara', 'Bhiwadi', 'bhiwadi', 13
  UNION ALL SELECT 'kotputli-behror', 'Neemrana', 'neemrana', 14
  UNION ALL SELECT 'rajsamand', 'Nathdwara', 'nathdwara', 15
  UNION ALL SELECT 'rajsamand', 'Kumbhalgarh', 'kumbhalgarh', 16
  UNION ALL SELECT 'sawai-madhopur', 'Ranthambore', 'ranthambore', 17
  UNION ALL SELECT 'jhunjhunu', 'Mandawa', 'mandawa', 18
  UNION ALL SELECT 'gangtok', 'Gangtok', 'gangtok', 0
  UNION ALL SELECT 'gyalshing', 'Gyalshing', 'gyalshing', 0
  UNION ALL SELECT 'mangan', 'Mangan', 'mangan', 0
  UNION ALL SELECT 'namchi', 'Namchi', 'namchi', 0
  UNION ALL SELECT 'pakyong', 'Pakyong', 'pakyong', 0
  UNION ALL SELECT 'soreng', 'Soreng', 'soreng', 0
  UNION ALL SELECT 'gyalshing', 'Pelling', 'pelling', 10
  UNION ALL SELECT 'mangan', 'Lachung', 'lachung', 11
  UNION ALL SELECT 'namchi', 'Ravangla', 'ravangla', 12
  UNION ALL SELECT 'adilabad', 'Adilabad', 'adilabad', 0
  UNION ALL SELECT 'bhadradri-kothagudem', 'Kothagudem', 'kothagudem', 0
  UNION ALL SELECT 'hanumakonda', 'Hanumakonda', 'hanumakonda', 0
  UNION ALL SELECT 'hyderabad', 'Hyderabad', 'hyderabad', 0
  UNION ALL SELECT 'jagtial', 'Jagtial', 'jagtial', 0
  UNION ALL SELECT 'jangaon', 'Jangaon', 'jangaon', 0
  UNION ALL SELECT 'jayashankar-bhupalpally', 'Bhupalpally', 'bhupalpally', 0
  UNION ALL SELECT 'jogulamba-gadwal', 'Gadwal', 'gadwal', 0
  UNION ALL SELECT 'kamareddy', 'Kamareddy', 'kamareddy', 0
  UNION ALL SELECT 'karimnagar', 'Karimnagar', 'karimnagar', 0
  UNION ALL SELECT 'khammam', 'Khammam', 'khammam', 0
  UNION ALL SELECT 'kumuram-bheem-asifabad', 'Asifabad', 'asifabad', 0
  UNION ALL SELECT 'mahabubabad', 'Mahabubabad', 'mahabubabad', 0
  UNION ALL SELECT 'mahabubnagar', 'Mahabubnagar', 'mahabubnagar', 0
  UNION ALL SELECT 'mancherial', 'Mancherial', 'mancherial', 0
  UNION ALL SELECT 'medak', 'Medak', 'medak', 0
  UNION ALL SELECT 'medchal-malkajgiri', 'Medchal', 'medchal', 0
  UNION ALL SELECT 'mulugu', 'Mulugu', 'mulugu', 0
  UNION ALL SELECT 'nagarkurnool', 'Nagarkurnool', 'nagarkurnool', 0
  UNION ALL SELECT 'nalgonda', 'Nalgonda', 'nalgonda', 0
  UNION ALL SELECT 'narayanpet', 'Narayanpet', 'narayanpet', 0
  UNION ALL SELECT 'nirmal', 'Nirmal', 'nirmal', 0
  UNION ALL SELECT 'nizamabad', 'Nizamabad', 'nizamabad', 0
  UNION ALL SELECT 'peddapalli', 'Peddapalli', 'peddapalli', 0
  UNION ALL SELECT 'rajanna-sircilla', 'Sircilla', 'sircilla', 0
  UNION ALL SELECT 'ranga-reddy', 'Ranga Reddy', 'ranga-reddy', 0
  UNION ALL SELECT 'sangareddy', 'Sangareddy', 'sangareddy', 0
  UNION ALL SELECT 'siddipet', 'Siddipet', 'siddipet', 0
  UNION ALL SELECT 'suryapet', 'Suryapet', 'suryapet', 0
  UNION ALL SELECT 'vikarabad', 'Vikarabad', 'vikarabad', 0
  UNION ALL SELECT 'wanaparthy', 'Wanaparthy', 'wanaparthy', 0
  UNION ALL SELECT 'warangal', 'Warangal', 'warangal', 0
  UNION ALL SELECT 'yadadri-bhuvanagiri', 'Bhongir', 'bhongir', 0
  UNION ALL SELECT 'hyderabad', 'Secunderabad', 'secunderabad', 10
  UNION ALL SELECT 'ranga-reddy', 'Gachibowli', 'gachibowli', 11
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'ranga-reddy' AS dslug, 'HITEC City' AS name, 'hitec-city' AS slug, 12 AS sort_order
  UNION ALL SELECT 'ranga-reddy', 'Madhapur', 'madhapur', 13
  UNION ALL SELECT 'ranga-reddy', 'Shamshabad', 'shamshabad', 14
  UNION ALL SELECT 'medchal-malkajgiri', 'Kukatpally', 'kukatpally', 15
  UNION ALL SELECT 'medchal-malkajgiri', 'Uppal', 'uppal', 16
  UNION ALL SELECT 'peddapalli', 'Ramagundam', 'ramagundam', 17
  UNION ALL SELECT 'bhadradri-kothagudem', 'Bhadrachalam', 'bhadrachalam', 18
  UNION ALL SELECT 'yadadri-bhuvanagiri', 'Yadagirigutta', 'yadagirigutta', 19
  UNION ALL SELECT 'dhalai', 'Ambassa', 'ambassa', 0
  UNION ALL SELECT 'gomati', 'Udaipur', 'udaipur-tripura', 0
  UNION ALL SELECT 'khowai', 'Khowai', 'khowai', 0
  UNION ALL SELECT 'north-tripura', 'Dharmanagar', 'dharmanagar', 0
  UNION ALL SELECT 'sepahijala', 'Bishramganj', 'bishramganj', 0
  UNION ALL SELECT 'south-tripura', 'Belonia', 'belonia', 0
  UNION ALL SELECT 'unakoti', 'Kailashahar', 'kailashahar', 0
  UNION ALL SELECT 'west-tripura', 'Agartala', 'agartala', 0
  UNION ALL SELECT 'agra', 'Agra', 'agra', 0
  UNION ALL SELECT 'aligarh', 'Aligarh', 'aligarh', 0
  UNION ALL SELECT 'ambedkar-nagar', 'Ambedkar Nagar', 'ambedkar-nagar', 0
  UNION ALL SELECT 'amethi', 'Amethi', 'amethi', 0
  UNION ALL SELECT 'amroha', 'Amroha', 'amroha', 0
  UNION ALL SELECT 'auraiya', 'Auraiya', 'auraiya', 0
  UNION ALL SELECT 'ayodhya', 'Ayodhya', 'ayodhya', 0
  UNION ALL SELECT 'azamgarh', 'Azamgarh', 'azamgarh', 0
  UNION ALL SELECT 'baghpat', 'Baghpat', 'baghpat', 0
  UNION ALL SELECT 'bahraich', 'Bahraich', 'bahraich', 0
  UNION ALL SELECT 'ballia', 'Ballia', 'ballia', 0
  UNION ALL SELECT 'balrampur', 'Balrampur', 'balrampur', 0
  UNION ALL SELECT 'banda', 'Banda', 'banda', 0
  UNION ALL SELECT 'barabanki', 'Barabanki', 'barabanki', 0
  UNION ALL SELECT 'bareilly', 'Bareilly', 'bareilly', 0
  UNION ALL SELECT 'basti', 'Basti', 'basti', 0
  UNION ALL SELECT 'bhadohi', 'Bhadohi', 'bhadohi', 0
  UNION ALL SELECT 'bijnor', 'Bijnor', 'bijnor', 0
  UNION ALL SELECT 'budaun', 'Budaun', 'budaun', 0
  UNION ALL SELECT 'bulandshahr', 'Bulandshahr', 'bulandshahr', 0
  UNION ALL SELECT 'chandauli', 'Chandauli', 'chandauli', 0
  UNION ALL SELECT 'chitrakoot', 'Chitrakoot', 'chitrakoot', 0
  UNION ALL SELECT 'deoria', 'Deoria', 'deoria', 0
  UNION ALL SELECT 'etah', 'Etah', 'etah', 0
  UNION ALL SELECT 'etawah', 'Etawah', 'etawah', 0
  UNION ALL SELECT 'farrukhabad', 'Farrukhabad', 'farrukhabad', 0
  UNION ALL SELECT 'fatehpur', 'Fatehpur', 'fatehpur', 0
  UNION ALL SELECT 'firozabad', 'Firozabad', 'firozabad', 0
  UNION ALL SELECT 'gautam-buddh-nagar', 'Noida', 'noida', 0
  UNION ALL SELECT 'ghaziabad', 'Ghaziabad', 'ghaziabad', 0
  UNION ALL SELECT 'ghazipur', 'Ghazipur', 'ghazipur', 0
  UNION ALL SELECT 'gonda', 'Gonda', 'gonda', 0
  UNION ALL SELECT 'gorakhpur', 'Gorakhpur', 'gorakhpur', 0
  UNION ALL SELECT 'hamirpur-uttar-pradesh', 'Hamirpur', 'hamirpur-uttar-pradesh', 0
  UNION ALL SELECT 'hapur', 'Hapur', 'hapur', 0
  UNION ALL SELECT 'hardoi', 'Hardoi', 'hardoi', 0
  UNION ALL SELECT 'hathras', 'Hathras', 'hathras', 0
  UNION ALL SELECT 'jalaun', 'Orai', 'orai', 0
  UNION ALL SELECT 'jaunpur', 'Jaunpur', 'jaunpur', 0
  UNION ALL SELECT 'jhansi', 'Jhansi', 'jhansi', 0
  UNION ALL SELECT 'kannauj', 'Kannauj', 'kannauj', 0
  UNION ALL SELECT 'kanpur-dehat', 'Kanpur Dehat', 'kanpur-dehat', 0
  UNION ALL SELECT 'kanpur-nagar', 'Kanpur', 'kanpur', 0
  UNION ALL SELECT 'kasganj', 'Kasganj', 'kasganj', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'kaushambi' AS dslug, 'Kaushambi' AS name, 'kaushambi' AS slug, 0 AS sort_order
  UNION ALL SELECT 'kheri', 'Lakhimpur', 'lakhimpur-uttar-pradesh', 0
  UNION ALL SELECT 'kushinagar', 'Kushinagar', 'kushinagar', 0
  UNION ALL SELECT 'lalitpur', 'Lalitpur', 'lalitpur', 0
  UNION ALL SELECT 'lucknow', 'Lucknow', 'lucknow', 0
  UNION ALL SELECT 'maharajganj', 'Maharajganj', 'maharajganj', 0
  UNION ALL SELECT 'mahoba', 'Mahoba', 'mahoba', 0
  UNION ALL SELECT 'mainpuri', 'Mainpuri', 'mainpuri', 0
  UNION ALL SELECT 'mathura', 'Mathura', 'mathura', 0
  UNION ALL SELECT 'mau', 'Mau', 'mau', 0
  UNION ALL SELECT 'meerut', 'Meerut', 'meerut', 0
  UNION ALL SELECT 'mirzapur', 'Mirzapur', 'mirzapur', 0
  UNION ALL SELECT 'moradabad', 'Moradabad', 'moradabad', 0
  UNION ALL SELECT 'muzaffarnagar', 'Muzaffarnagar', 'muzaffarnagar', 0
  UNION ALL SELECT 'pilibhit', 'Pilibhit', 'pilibhit', 0
  UNION ALL SELECT 'pratapgarh-uttar-pradesh', 'Pratapgarh', 'pratapgarh-uttar-pradesh', 0
  UNION ALL SELECT 'prayagraj', 'Prayagraj', 'prayagraj', 0
  UNION ALL SELECT 'raebareli', 'Raebareli', 'raebareli', 0
  UNION ALL SELECT 'rampur', 'Rampur', 'rampur', 0
  UNION ALL SELECT 'saharanpur', 'Saharanpur', 'saharanpur', 0
  UNION ALL SELECT 'sambhal', 'Sambhal', 'sambhal', 0
  UNION ALL SELECT 'sant-kabir-nagar', 'Khalilabad', 'khalilabad', 0
  UNION ALL SELECT 'shahjahanpur', 'Shahjahanpur', 'shahjahanpur', 0
  UNION ALL SELECT 'shamli', 'Shamli', 'shamli', 0
  UNION ALL SELECT 'shravasti', 'Shravasti', 'shravasti', 0
  UNION ALL SELECT 'siddharthnagar', 'Siddharthnagar', 'siddharthnagar', 0
  UNION ALL SELECT 'sitapur', 'Sitapur', 'sitapur', 0
  UNION ALL SELECT 'sonbhadra', 'Robertsganj', 'robertsganj', 0
  UNION ALL SELECT 'sultanpur', 'Sultanpur', 'sultanpur', 0
  UNION ALL SELECT 'unnao', 'Unnao', 'unnao', 0
  UNION ALL SELECT 'varanasi', 'Varanasi', 'varanasi', 0
  UNION ALL SELECT 'gautam-buddh-nagar', 'Greater Noida', 'greater-noida', 10
  UNION ALL SELECT 'mathura', 'Vrindavan', 'vrindavan', 11
  UNION ALL SELECT 'agra', 'Fatehpur Sikri', 'fatehpur-sikri', 12
  UNION ALL SELECT 'varanasi', 'Sarnath', 'sarnath', 13
  UNION ALL SELECT 'ghaziabad', 'Modinagar', 'modinagar', 14
  UNION ALL SELECT 'kheri', 'Dudhwa', 'dudhwa', 15
  UNION ALL SELECT 'almora', 'Almora', 'almora', 0
  UNION ALL SELECT 'bageshwar', 'Bageshwar', 'bageshwar', 0
  UNION ALL SELECT 'chamoli', 'Gopeshwar', 'gopeshwar', 0
  UNION ALL SELECT 'champawat', 'Champawat', 'champawat', 0
  UNION ALL SELECT 'dehradun', 'Dehradun', 'dehradun', 0
  UNION ALL SELECT 'haridwar', 'Haridwar', 'haridwar', 0
  UNION ALL SELECT 'nainital', 'Nainital', 'nainital', 0
  UNION ALL SELECT 'pauri-garhwal', 'Pauri', 'pauri', 0
  UNION ALL SELECT 'pithoragarh', 'Pithoragarh', 'pithoragarh', 0
  UNION ALL SELECT 'rudraprayag', 'Rudraprayag', 'rudraprayag', 0
  UNION ALL SELECT 'tehri-garhwal', 'New Tehri', 'new-tehri', 0
  UNION ALL SELECT 'udham-singh-nagar', 'Rudrapur', 'rudrapur', 0
  UNION ALL SELECT 'uttarkashi', 'Uttarkashi', 'uttarkashi', 0
  UNION ALL SELECT 'dehradun', 'Mussoorie', 'mussoorie', 10
  UNION ALL SELECT 'dehradun', 'Rishikesh', 'rishikesh', 11
  UNION ALL SELECT 'haridwar', 'Roorkee', 'roorkee', 12
  UNION ALL SELECT 'nainital', 'Haldwani', 'haldwani', 13
  UNION ALL SELECT 'nainital', 'Ramnagar', 'ramnagar', 14
  UNION ALL SELECT 'nainital', 'Bhimtal', 'bhimtal', 15
  UNION ALL SELECT 'almora', 'Ranikhet', 'ranikhet', 16
  UNION ALL SELECT 'chamoli', 'Auli', 'auli', 17
  UNION ALL SELECT 'chamoli', 'Badrinath', 'badrinath', 18
  UNION ALL SELECT 'rudraprayag', 'Kedarnath', 'kedarnath', 19
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'pauri-garhwal' AS dslug, 'Kotdwar' AS name, 'kotdwar' AS slug, 20 AS sort_order
  UNION ALL SELECT 'pauri-garhwal', 'Lansdowne', 'lansdowne', 21
  UNION ALL SELECT 'pithoragarh', 'Munsiyari', 'munsiyari', 22
  UNION ALL SELECT 'udham-singh-nagar', 'Kashipur', 'kashipur', 23
  UNION ALL SELECT 'alipurduar', 'Alipurduar', 'alipurduar', 0
  UNION ALL SELECT 'bankura', 'Bankura', 'bankura', 0
  UNION ALL SELECT 'birbhum', 'Suri', 'suri', 0
  UNION ALL SELECT 'cooch-behar', 'Cooch Behar', 'cooch-behar', 0
  UNION ALL SELECT 'dakshin-dinajpur', 'Balurghat', 'balurghat', 0
  UNION ALL SELECT 'darjeeling', 'Darjeeling', 'darjeeling', 0
  UNION ALL SELECT 'hooghly', 'Chinsurah', 'chinsurah', 0
  UNION ALL SELECT 'howrah', 'Howrah', 'howrah', 0
  UNION ALL SELECT 'jalpaiguri', 'Jalpaiguri', 'jalpaiguri', 0
  UNION ALL SELECT 'jhargram', 'Jhargram', 'jhargram', 0
  UNION ALL SELECT 'kalimpong', 'Kalimpong', 'kalimpong', 0
  UNION ALL SELECT 'kolkata', 'Kolkata', 'kolkata', 0
  UNION ALL SELECT 'malda', 'Malda', 'malda', 0
  UNION ALL SELECT 'murshidabad', 'Baharampur', 'baharampur', 0
  UNION ALL SELECT 'nadia', 'Krishnanagar', 'krishnanagar', 0
  UNION ALL SELECT 'north-24-parganas', 'Barasat', 'barasat', 0
  UNION ALL SELECT 'paschim-bardhaman', 'Asansol', 'asansol', 0
  UNION ALL SELECT 'paschim-medinipur', 'Midnapore', 'midnapore', 0
  UNION ALL SELECT 'purba-bardhaman', 'Bardhaman', 'bardhaman', 0
  UNION ALL SELECT 'purba-medinipur', 'Tamluk', 'tamluk', 0
  UNION ALL SELECT 'purulia', 'Purulia', 'purulia', 0
  UNION ALL SELECT 'south-24-parganas', 'Alipore', 'alipore', 0
  UNION ALL SELECT 'uttar-dinajpur', 'Raiganj', 'raiganj', 0
  UNION ALL SELECT 'darjeeling', 'Siliguri', 'siliguri', 10
  UNION ALL SELECT 'darjeeling', 'Kurseong', 'kurseong', 11
  UNION ALL SELECT 'paschim-bardhaman', 'Durgapur', 'durgapur', 12
  UNION ALL SELECT 'paschim-medinipur', 'Kharagpur', 'kharagpur', 13
  UNION ALL SELECT 'purba-medinipur', 'Haldia', 'haldia', 14
  UNION ALL SELECT 'purba-medinipur', 'Digha', 'digha', 15
  UNION ALL SELECT 'birbhum', 'Santiniketan', 'santiniketan', 16
  UNION ALL SELECT 'birbhum', 'Bolpur', 'bolpur', 17
  UNION ALL SELECT 'north-24-parganas', 'Salt Lake', 'salt-lake', 18
  UNION ALL SELECT 'north-24-parganas', 'New Town', 'new-town', 19
  UNION ALL SELECT 'north-24-parganas', 'Dum Dum', 'dum-dum', 20
  UNION ALL SELECT 'south-24-parganas', 'Diamond Harbour', 'diamond-harbour', 21
  UNION ALL SELECT 'south-24-parganas', 'Sundarbans', 'sundarbans', 22
  UNION ALL SELECT 'hooghly', 'Serampore', 'serampore', 23
  UNION ALL SELECT 'hooghly', 'Chandannagar', 'chandannagar', 24
  UNION ALL SELECT 'nicobar', 'Car Nicobar', 'car-nicobar', 0
  UNION ALL SELECT 'north-and-middle-andaman', 'Mayabunder', 'mayabunder', 0
  UNION ALL SELECT 'south-andaman', 'Sri Vijaya Puram', 'sri-vijaya-puram', 0
  UNION ALL SELECT 'south-andaman', 'Swaraj Dweep', 'swaraj-dweep', 10
  UNION ALL SELECT 'south-andaman', 'Shaheed Dweep', 'shaheed-dweep', 11
  UNION ALL SELECT 'north-and-middle-andaman', 'Diglipur', 'diglipur', 12
  UNION ALL SELECT 'north-and-middle-andaman', 'Rangat', 'rangat', 13
  UNION ALL SELECT 'chandigarh', 'Chandigarh', 'chandigarh', 0
  UNION ALL SELECT 'dadra-and-nagar-haveli', 'Silvassa', 'silvassa', 0
  UNION ALL SELECT 'daman', 'Daman', 'daman', 0
  UNION ALL SELECT 'diu', 'Diu', 'diu', 0
  UNION ALL SELECT 'central-delhi', 'Central Delhi', 'central-delhi', 0
  UNION ALL SELECT 'east-delhi', 'East Delhi', 'east-delhi', 0
  UNION ALL SELECT 'new-delhi', 'New Delhi', 'new-delhi', 0
  UNION ALL SELECT 'north-delhi', 'North Delhi', 'north-delhi', 0
  UNION ALL SELECT 'north-east-delhi', 'North East Delhi', 'north-east-delhi', 0
  UNION ALL SELECT 'north-west-delhi', 'North West Delhi', 'north-west-delhi', 0
  UNION ALL SELECT 'shahdara', 'Shahdara', 'shahdara', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'south-delhi' AS dslug, 'South Delhi' AS name, 'south-delhi' AS slug, 0 AS sort_order
  UNION ALL SELECT 'south-east-delhi', 'South East Delhi', 'south-east-delhi', 0
  UNION ALL SELECT 'south-west-delhi', 'South West Delhi', 'south-west-delhi', 0
  UNION ALL SELECT 'west-delhi', 'West Delhi', 'west-delhi', 0
  UNION ALL SELECT 'new-delhi', 'Connaught Place', 'connaught-place', 10
  UNION ALL SELECT 'new-delhi', 'Chanakyapuri', 'chanakyapuri', 11
  UNION ALL SELECT 'central-delhi', 'Karol Bagh', 'karol-bagh', 12
  UNION ALL SELECT 'central-delhi', 'Chandni Chowk', 'chandni-chowk', 13
  UNION ALL SELECT 'north-west-delhi', 'Rohini', 'rohini', 14
  UNION ALL SELECT 'north-west-delhi', 'Pitampura', 'pitampura', 15
  UNION ALL SELECT 'south-delhi', 'Saket', 'saket', 16
  UNION ALL SELECT 'south-delhi', 'Hauz Khas', 'hauz-khas', 17
  UNION ALL SELECT 'south-delhi', 'Mehrauli', 'mehrauli', 18
  UNION ALL SELECT 'south-east-delhi', 'Nehru Place', 'nehru-place', 19
  UNION ALL SELECT 'south-east-delhi', 'Lajpat Nagar', 'lajpat-nagar', 20
  UNION ALL SELECT 'south-east-delhi', 'Okhla', 'okhla', 21
  UNION ALL SELECT 'south-west-delhi', 'Dwarka', 'dwarka-delhi', 22
  UNION ALL SELECT 'south-west-delhi', 'Aerocity', 'aerocity', 23
  UNION ALL SELECT 'west-delhi', 'Janakpuri', 'janakpuri', 24
  UNION ALL SELECT 'west-delhi', 'Rajouri Garden', 'rajouri-garden', 25
  UNION ALL SELECT 'east-delhi', 'Preet Vihar', 'preet-vihar', 26
  UNION ALL SELECT 'east-delhi', 'Mayur Vihar', 'mayur-vihar', 27
  UNION ALL SELECT 'north-delhi', 'Civil Lines', 'civil-lines', 28
  UNION ALL SELECT 'anantnag', 'Anantnag', 'anantnag', 0
  UNION ALL SELECT 'bandipora', 'Bandipora', 'bandipora', 0
  UNION ALL SELECT 'baramulla', 'Baramulla', 'baramulla', 0
  UNION ALL SELECT 'budgam', 'Budgam', 'budgam', 0
  UNION ALL SELECT 'doda', 'Doda', 'doda', 0
  UNION ALL SELECT 'ganderbal', 'Ganderbal', 'ganderbal', 0
  UNION ALL SELECT 'jammu', 'Jammu', 'jammu', 0
  UNION ALL SELECT 'kathua', 'Kathua', 'kathua', 0
  UNION ALL SELECT 'kishtwar', 'Kishtwar', 'kishtwar', 0
  UNION ALL SELECT 'kulgam', 'Kulgam', 'kulgam', 0
  UNION ALL SELECT 'kupwara', 'Kupwara', 'kupwara', 0
  UNION ALL SELECT 'poonch', 'Poonch', 'poonch', 0
  UNION ALL SELECT 'pulwama', 'Pulwama', 'pulwama', 0
  UNION ALL SELECT 'rajouri', 'Rajouri', 'rajouri', 0
  UNION ALL SELECT 'ramban', 'Ramban', 'ramban', 0
  UNION ALL SELECT 'reasi', 'Reasi', 'reasi', 0
  UNION ALL SELECT 'samba', 'Samba', 'samba', 0
  UNION ALL SELECT 'shopian', 'Shopian', 'shopian', 0
  UNION ALL SELECT 'srinagar', 'Srinagar', 'srinagar', 0
  UNION ALL SELECT 'udhampur', 'Udhampur', 'udhampur', 0
  UNION ALL SELECT 'baramulla', 'Gulmarg', 'gulmarg', 10
  UNION ALL SELECT 'anantnag', 'Pahalgam', 'pahalgam', 11
  UNION ALL SELECT 'ganderbal', 'Sonamarg', 'sonamarg', 12
  UNION ALL SELECT 'reasi', 'Katra', 'katra', 13
  UNION ALL SELECT 'udhampur', 'Patnitop', 'patnitop', 14
  UNION ALL SELECT 'kargil', 'Kargil', 'kargil', 0
  UNION ALL SELECT 'leh', 'Leh', 'leh', 0
  UNION ALL SELECT 'leh', 'Nubra', 'nubra', 10
  UNION ALL SELECT 'leh', 'Pangong', 'pangong', 11
  UNION ALL SELECT 'kargil', 'Zanskar', 'zanskar', 12
  UNION ALL SELECT 'kargil', 'Drass', 'drass', 13
  UNION ALL SELECT 'lakshadweep', 'Kavaratti', 'kavaratti', 0
  UNION ALL SELECT 'lakshadweep', 'Agatti', 'agatti', 10
  UNION ALL SELECT 'lakshadweep', 'Minicoy', 'minicoy', 11
  UNION ALL SELECT 'lakshadweep', 'Bangaram', 'bangaram', 12
  UNION ALL SELECT 'puducherry', 'Puducherry', 'puducherry', 0
  UNION ALL SELECT 'karaikal', 'Karaikal', 'karaikal', 0
) v ON v.dslug = d.slug;

INSERT IGNORE INTO cities (district_id, name, slug, is_active, is_featured, sort_order)
SELECT d.id, v.name, v.slug, 1, 0, v.sort_order FROM districts d JOIN (
SELECT 'mahe' AS dslug, 'Mahe' AS name, 'mahe' AS slug, 0 AS sort_order
  UNION ALL SELECT 'yanam', 'Yanam', 'yanam', 0
  UNION ALL SELECT 'puducherry', 'Ozhukarai', 'ozhukarai', 10
  UNION ALL SELECT 'puducherry', 'Villianur', 'villianur', 11
  UNION ALL SELECT 'puducherry', 'Ariyankuppam', 'ariyankuppam', 12
  UNION ALL SELECT 'puducherry', 'Bahour', 'bahour', 13
  UNION ALL SELECT 'puducherry', 'Mannadipet', 'mannadipet', 14
  UNION ALL SELECT 'puducherry', 'Nettapakkam', 'nettapakkam', 15
  UNION ALL SELECT 'karaikal', 'Thirunallar', 'thirunallar', 16
  UNION ALL SELECT 'karaikal', 'Neravy', 'neravy', 17
  UNION ALL SELECT 'karaikal', 'Kottucherry', 'kottucherry', 18
  UNION ALL SELECT 'karaikal', 'Thirumalairayanpattinam', 'thirumalairayanpattinam', 19
  UNION ALL SELECT 'karaikal', 'Nedungadu', 'nedungadu', 20
) v ON v.dslug = d.slug;

-- Neighbourhoods of Puducherry town
INSERT IGNORE INTO areas (city_id, name, slug, is_active)
SELECT c.id, v.name, v.slug, 1 FROM cities c JOIN (
SELECT 'White Town' AS name, 'white-town' AS slug
  UNION ALL SELECT 'Heritage Town', 'heritage-town'
  UNION ALL SELECT 'Lawspet', 'lawspet'
  UNION ALL SELECT 'Muthialpet', 'muthialpet'
  UNION ALL SELECT 'Reddiarpalayam', 'reddiarpalayam'
  UNION ALL SELECT 'Kalapet', 'kalapet'
  UNION ALL SELECT 'Mudaliarpet', 'mudaliarpet'
  UNION ALL SELECT 'Thattanchavady', 'thattanchavady'
  UNION ALL SELECT 'Saram', 'saram'
  UNION ALL SELECT 'Orleanpet', 'orleanpet'
  UNION ALL SELECT 'Kosapalayam', 'kosapalayam'
  UNION ALL SELECT 'Nellithope', 'nellithope'
  UNION ALL SELECT 'Uppalam', 'uppalam'
  UNION ALL SELECT 'Vaithikuppam', 'vaithikuppam'
  UNION ALL SELECT 'Solaram', 'solaram'
  UNION ALL SELECT 'Gorimedu', 'gorimedu'
  UNION ALL SELECT 'Auroville Road', 'auroville-road'
  UNION ALL SELECT 'Serenity Beach', 'serenity-beach'
  UNION ALL SELECT 'Paradise Beach', 'paradise-beach'
  UNION ALL SELECT 'Rock Beach', 'rock-beach'
  UNION ALL SELECT 'Promenade Beach', 'promenade-beach'
  UNION ALL SELECT 'Pondicherry University', 'pondicherry-university'
) v WHERE c.slug = 'puducherry';

-- 002 mapped "Pondicherry Road Villupuram" to Viluppuram. Puducherry is its own place now,
-- so that alias would send people typing "Pondicherry" to the wrong district.
DELETE FROM district_aliases WHERE alias_norm = 'pondicherry-road-villupuram';

-- Alternate and former names (each points to exactly one district)
INSERT IGNORE INTO district_aliases (alias, alias_norm, district_id)
SELECT v.alias, v.alias_norm, d.id FROM districts d JOIN (
SELECT 'Pondicherry' AS alias, 'pondicherry' AS alias_norm, 'puducherry' AS dslug
  UNION ALL SELECT 'Pondy', 'pondy', 'puducherry'
  UNION ALL SELECT 'Mahé', 'mahé', 'mahe'
  UNION ALL SELECT 'Mayyazhi', 'mayyazhi', 'mahe'
  UNION ALL SELECT 'Bangalore', 'bangalore', 'bengaluru-urban'
  UNION ALL SELECT 'Ramanagara', 'ramanagara', 'bengaluru-south'
  UNION ALL SELECT 'Mysore', 'mysore', 'mysuru'
  UNION ALL SELECT 'Mangalore', 'mangalore', 'dakshina-kannada'
  UNION ALL SELECT 'Belgaum', 'belgaum', 'belagavi'
  UNION ALL SELECT 'Gulbarga', 'gulbarga', 'kalaburagi'
  UNION ALL SELECT 'Bellary', 'bellary', 'ballari'
  UNION ALL SELECT 'Shimoga', 'shimoga', 'shivamogga'
  UNION ALL SELECT 'Tumkur', 'tumkur', 'tumakuru'
  UNION ALL SELECT 'Chikmagalur', 'chikmagalur', 'chikkamagaluru'
  UNION ALL SELECT 'Hubli', 'hubli', 'dharwad'
  UNION ALL SELECT 'Hospet', 'hospet', 'vijayanagara'
  UNION ALL SELECT 'Karwar', 'karwar', 'uttara-kannada'
  UNION ALL SELECT 'Bombay', 'bombay', 'mumbai-city'
  UNION ALL SELECT 'Poona', 'poona', 'pune'
  UNION ALL SELECT 'Ahmednagar', 'ahmednagar', 'ahilyanagar'
  UNION ALL SELECT 'Osmanabad', 'osmanabad', 'dharashiv'
  UNION ALL SELECT 'Sambhajinagar', 'sambhajinagar', 'chhatrapati-sambhajinagar'
  UNION ALL SELECT 'Calcutta', 'calcutta', 'kolkata'
  UNION ALL SELECT 'Burdwan', 'burdwan', 'purba-bardhaman'
  UNION ALL SELECT 'Bidhannagar', 'bidhannagar', 'north-24-parganas'
  UNION ALL SELECT 'Gurgaon', 'gurgaon', 'gurugram'
  UNION ALL SELECT 'Mewat', 'mewat', 'nuh'
  UNION ALL SELECT 'Vizag', 'vizag', 'visakhapatnam'
  UNION ALL SELECT 'Anantapuramu', 'anantapuramu', 'anantapur'
  UNION ALL SELECT 'Cuddapah', 'cuddapah', 'ysr-kadapa'
  UNION ALL SELECT 'Rajahmundry', 'rajahmundry', 'east-godavari'
  UNION ALL SELECT 'Konaseema', 'konaseema', 'dr-br-ambedkar-konaseema'
  UNION ALL SELECT 'Trivandrum', 'trivandrum', 'thiruvananthapuram'
  UNION ALL SELECT 'Cochin', 'cochin', 'ernakulam'
  UNION ALL SELECT 'Calicut', 'calicut', 'kozhikode'
  UNION ALL SELECT 'Alleppey', 'alleppey', 'alappuzha'
  UNION ALL SELECT 'Quilon', 'quilon', 'kollam'
  UNION ALL SELECT 'Trichur', 'trichur', 'thrissur'
  UNION ALL SELECT 'Palghat', 'palghat', 'palakkad'
  UNION ALL SELECT 'Cannanore', 'cannanore', 'kannur'
  UNION ALL SELECT 'Allahabad', 'allahabad', 'prayagraj'
  UNION ALL SELECT 'Faizabad', 'faizabad', 'ayodhya'
  UNION ALL SELECT 'Benares', 'benares', 'varanasi'
  UNION ALL SELECT 'Banaras', 'banaras', 'varanasi'
  UNION ALL SELECT 'Kashi', 'kashi', 'varanasi'
  UNION ALL SELECT 'Lakhimpur Kheri', 'lakhimpur kheri', 'kheri'
  UNION ALL SELECT 'Sant Ravidas Nagar', 'sant ravidas nagar', 'bhadohi'
  UNION ALL SELECT 'Baroda', 'baroda', 'vadodara'
  UNION ALL SELECT 'Kachchh', 'kachchh', 'kutch'
  UNION ALL SELECT 'Mahesana', 'mahesana', 'mehsana'
  UNION ALL SELECT 'The Dangs', 'the dangs', 'dang'
  UNION ALL SELECT 'Hoshangabad', 'hoshangabad', 'narmadapuram'
  UNION ALL SELECT 'Simla', 'simla', 'shimla'
  UNION ALL SELECT 'Dharamsala', 'dharamsala', 'kangra'
  UNION ALL SELECT 'Ropar', 'ropar', 'rupnagar'
  UNION ALL SELECT 'SAS Nagar', 'sas nagar', 'sahibzada-ajit-singh-nagar'
  UNION ALL SELECT 'SBS Nagar', 'sbs nagar', 'shaheed-bhagat-singh-nagar'
  UNION ALL SELECT 'Firozpur', 'firozpur', 'ferozepur'
  UNION ALL SELECT 'Gauhati', 'gauhati', 'kamrup-metropolitan'
  UNION ALL SELECT 'Karimganj', 'karimganj', 'sribhumi'
  UNION ALL SELECT 'Hanamkonda', 'hanamkonda', 'hanumakonda'
  UNION ALL SELECT 'Rangareddy', 'rangareddy', 'ranga-reddy'
  UNION ALL SELECT 'Baleswar', 'baleswar', 'balasore'
  UNION ALL SELECT 'Keonjhar', 'keonjhar', 'kendujhar'
  UNION ALL SELECT 'Sonepur', 'sonepur', 'subarnapur'
  UNION ALL SELECT 'Bolangir', 'bolangir', 'balangir'
  UNION ALL SELECT 'Saiha', 'saiha', 'siaha'
  UNION ALL SELECT 'Chumukedima', 'chumukedima', 'chumoukedima'
  UNION ALL SELECT 'Port Blair', 'port blair', 'south-andaman'
  UNION ALL SELECT 'Havelock Island', 'havelock island', 'south-andaman'
  UNION ALL SELECT 'Neil Island', 'neil island', 'south-andaman'
  UNION ALL SELECT 'Delhi', 'delhi', 'new-delhi'
  UNION ALL SELECT 'NCT of Delhi', 'nct of delhi', 'new-delhi'
  UNION ALL SELECT 'Cherrapunji', 'cherrapunji', 'east-khasi-hills'
  UNION ALL SELECT 'Ganganagar', 'ganganagar', 'sri-ganganagar'
  UNION ALL SELECT 'Chittaurgarh', 'chittaurgarh', 'chittorgarh'
  UNION ALL SELECT 'Jalor', 'jalor', 'jalore'
  UNION ALL SELECT 'Leh Ladakh', 'leh ladakh', 'leh'
) v ON v.dslug = d.slug;
