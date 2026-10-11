<?php

/**
 * Purchase-invoice photos (gas purchases, construction materials).
 *
 * Phone cameras produce 4-12 MB JPEGs. Storing those for every invoice would
 * eat the hosting quota for no benefit, so every upload is re-encoded the way
 * Facebook/WhatsApp do: capped at a long edge of `max_edge` px and squeezed
 * to roughly `target_kb`. 2000 px is about 170 dpi on A4 - still crisp enough
 * to read small print on an invoice when it is printed for an audit.
 */
return [
    // Private disk (storage/app/private): invoices are financial records and
    // must only be reachable through an authenticated, permission-gated route.
    'disk' => env('INVOICE_DISK', 'local'),

    'max_edge' => 2000,
    'target_kb' => 350,
    'start_quality' => 82,
    'min_quality' => 50,

    // Largest file the API accepts *before* compression (the browser already
    // shrinks it first, so real uploads are usually well under 1 MB).
    'max_upload_kb' => 15360,

    // Decoding a bitmap needs ~5 bytes per pixel in memory; refuse absurd sizes.
    'max_pixels' => 50_000_000,

    // Audit pack: one A4 page per invoice, so cap the batch to keep the PDF
    // inside typical shared-hosting memory/time limits.
    'audit_pack_max' => 120,
];
