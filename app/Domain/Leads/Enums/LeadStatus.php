<?php

namespace App\Domain\Leads\Enums;

enum LeadStatus: string
{
    case New = 'NEW';
    case Researching = 'RESEARCHING';
    case Qualified = 'QUALIFIED';
    case Assigned = 'ASSIGNED';
    case Contacted = 'CONTACTED';
    case Interested = 'INTERESTED';
    case Meeting = 'MEETING';
    case Proposal = 'PROPOSAL';
    case Negotiation = 'NEGOTIATION';
    case Won = 'WON';
    case Lost = 'LOST';
    case Disqualified = 'DISQUALIFIED';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Researching => 'Riset',
            self::Qualified => 'Terkualifikasi',
            self::Assigned => 'Ditugaskan',
            self::Contacted => 'Dihubungi',
            self::Interested => 'Tertarik',
            self::Meeting => 'Meeting',
            self::Proposal => 'Proposal',
            self::Negotiation => 'Negosiasi',
            self::Won => 'Menang',
            self::Lost => 'Kalah',
            self::Disqualified => 'Tidak Layak',
        };
    }

    /** @return LeadStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Researching, self::Qualified, self::Disqualified],
            self::Researching => [self::Qualified, self::Disqualified],
            self::Qualified => [self::Assigned, self::Disqualified],
            self::Assigned => [self::Contacted, self::Disqualified],
            self::Contacted => [self::Interested, self::Lost, self::Disqualified],
            self::Interested => [self::Meeting, self::Lost],
            self::Meeting => [self::Proposal, self::Lost],
            self::Proposal => [self::Negotiation, self::Lost],
            self::Negotiation => [self::Won, self::Lost],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
