<?php

namespace App\Exceptions\Learning;

/**
 * A learning delete / restore / purge that would orphan or lose related data
 * (e.g. a category that still has courses, a room that is live). The message
 * is user-facing — controllers flash it back as an error.
 */
class LearningDeletionBlocked extends \RuntimeException
{
}
