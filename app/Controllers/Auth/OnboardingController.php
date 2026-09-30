<?php

declare(strict_types=1);

namespace NEvents\Controllers\Auth;

use NEvents\Core\Request;
use NEvents\Core\Response;
use NEvents\Core\View;
use NEvents\Repositories\UserRepository;
use NEvents\Repositories\CategoryRepository;
use NEvents\Services\Location\DistrictService;

class OnboardingController
{
    private const TOTAL_STEPS = 3;

    public function __construct(
        private UserRepository     $users,
        private CategoryRepository $categories,
        private DistrictService    $districts,
    ) {}

    public function step(Request $request): Response
    {
        $step = max(1, min(self::TOTAL_STEPS, (int) $request->query('step', 1)));

        return View::make('onboarding/step', [
            'title'      => 'Set Up Your Profile',
            'step'       => $step,
            'total'      => self::TOTAL_STEPS,
            'categories' => $this->categories->topLevel(),
            'district'   => ($d = $this->districts->activeDistrict($request->userId())) ? $this->districts->findById($d['id']) : null,
        ]);
    }

    public function save(Request $request): Response
    {
        $step   = max(1, min(self::TOTAL_STEPS, (int) $request->post('step', 1)));
        $userId = $request->userId();
        $data   = $request->all();

        match ($step) {
            1 => $this->saveLocation($userId, $data),
            2 => $this->saveInterests($userId, $data),
            3 => $this->finalize($userId),
            default => null,
        };

        if ($step >= self::TOTAL_STEPS) {
            $_SESSION['onboarding_done'] = true;
            $_SESSION['flash_success']   = 'Welcome to N Events!';
            return Response::redirect('/dashboard');
        }

        return Response::redirect('/onboarding?step=' . ($step + 1));
    }

    private function saveLocation(int $userId, array $data): void
    {
        $districtId = (int) ($data['district_id'] ?? 0);
        if ($districtId > 0 && $this->districts->isValidActiveDistrict($districtId)) {
            $this->users->setDistrictPreference($userId, $districtId);
        }
    }

    private function saveInterests(int $userId, array $data): void
    {
        $ids = array_map('intval', (array) ($data['category_ids'] ?? []));
        if ($ids) {
            $this->users->setInterests($userId, $ids);
        }
    }

    private function finalize(int $userId): void
    {
        $this->users->update($userId, ['onboarding_done' => 1, 'onboarding_step' => self::TOTAL_STEPS]);
    }
}
