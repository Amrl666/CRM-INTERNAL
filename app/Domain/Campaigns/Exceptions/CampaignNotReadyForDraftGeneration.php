<?php

namespace App\Domain\Campaigns\Exceptions;

use InvalidArgumentException;

final class CampaignNotReadyForDraftGeneration extends InvalidArgumentException {}
