<?php

declare(strict_types=1);

namespace Signori\Responses;

/**
 * A field placed on a document — e.g. a signature box at a known position
 * on a given page. Created via ``Documents::placeFields()``.
 *
 * Coordinates are in PDF points with **top-left origin** (UI convention).
 * The signing service flips the y-axis when rendering the field onto the
 * PDF, so callers can use the same coords they would in a UI overlay.
 */
final class DocumentFieldResponse extends BaseResponse
{
    public readonly string  $id;
    public readonly string  $documentId;
    public readonly string  $fieldType;
    public readonly ?string $assignedTo;
    public readonly int     $page;
    public readonly float   $x;
    public readonly float   $y;
    public readonly float   $width;
    public readonly float   $height;
    public readonly bool    $required;

    public static function from(array $data): self
    {
        $r = new self($data);
        $r->id         = $r->str('id');
        $r->documentId = $r->str('document_id');
        $r->fieldType  = $r->str('field_type');
        $r->assignedTo = $r->nullable('assigned_to');
        $r->page       = $r->int('page', 1);
        $r->x          = (float) ($data['x'] ?? 0);
        $r->y          = (float) ($data['y'] ?? 0);
        $r->width      = (float) ($data['width'] ?? 0);
        $r->height     = (float) ($data['height'] ?? 0);
        $r->required   = $r->bool('required', true);
        return $r;
    }
}
