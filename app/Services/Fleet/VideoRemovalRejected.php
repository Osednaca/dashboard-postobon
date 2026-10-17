<?php

namespace App\Services\Fleet;

use RuntimeException;

/** An actionable removal message safe to display to the operator. */
class VideoRemovalRejected extends RuntimeException {}
