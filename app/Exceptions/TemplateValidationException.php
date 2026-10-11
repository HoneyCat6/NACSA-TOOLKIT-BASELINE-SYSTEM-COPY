<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An uploaded workbook is not an unmodified NCSB v1.4 toolkit (FR-11, DECISIONS D-10).
 *
 * The message is user-facing and names the first item that failed.
 */
class TemplateValidationException extends RuntimeException {}
