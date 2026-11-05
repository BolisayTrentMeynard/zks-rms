<?php

namespace App\Enums;

enum ProofStatus: string
{
    case ForReview = 'for_review';
    case Accepted = 'accepted';
    case Flagged = 'flagged';
}
