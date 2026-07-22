<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/PartnerRepository.php';

class PartnerService
{
    private PartnerRepository $partnerRepository;

    public function __construct()
    {
        $this->partnerRepository = new PartnerRepository();
    }

    public function findActive(): array
    {
        return $this->partnerRepository->findActive();
    }
}
