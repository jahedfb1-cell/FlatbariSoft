<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A staff member's KYC form and papers. The page shows what is still missing (details and files) so the file is
 * never left half-done; the papers are kept privately and only handed out through the authenticated API.
 */
class StaffKycService
{
    public const FIELDS = [
        'father_name', 'mother_name', 'spouse_name', 'date_of_birth', 'gender', 'marital_status', 'blood_group', 'religion',
        'alt_mobile', 'email', 'present_address', 'permanent_address', 'emergency_name', 'emergency_phone', 'emergency_relation',
        'education', 'experience', 'reference_name', 'reference_phone', 'other_info', 'family_members',
    ];

    /** What a complete file holds: details first, then the three papers that matter. */
    private const REQUIRED_DETAILS = ['nid', 'father_name', 'date_of_birth', 'present_address', 'permanent_address', 'emergency_name', 'emergency_phone'];

    private const REQUIRED_PAPERS = ['CV', 'NID', 'PHOTO'];

    public function ready(): bool
    {
        return Schema::hasColumn('staff', 'father_name') && Schema::hasTable('staff_documents');
    }

    /** @return array<string, mixed> */
    public function present(Staff $staff): array
    {
        $docs = $this->ready() ? StaffDocument::where('staff_id', $staff->id)->orderBy('type')->orderBy('id')->get() : collect();
        $missing = [];
        foreach (self::REQUIRED_DETAILS as $f) {
            if (blank($staff->{$f})) {
                $missing[] = $f;
            }
        }
        foreach (self::REQUIRED_PAPERS as $type) {
            if (! $docs->contains('type', $type)) {
                $missing[] = 'doc:'.$type;
            }
        }

        $out = [
            'staff_id' => $staff->id, 'name' => $staff->name, 'designation' => $staff->designation, 'mobile' => $staff->mobile,
            'nid' => $staff->nid, 'join_date' => $staff->join_date?->toDateString(), 'status' => $staff->status,
            'complete' => $missing === [], 'missing' => $missing, 'ready' => $this->ready(),
            'kyc_updated_at' => $this->ready() ? $staff->kyc_updated_at?->timezone('Asia/Dhaka')->format('Y-m-d H:i') : null,
            'documents' => $docs->map(fn (StaffDocument $d) => ['id' => $d->id, 'type' => $d->type, 'name' => $d->original_name, 'mime' => $d->mime, 'size' => $d->size])->values(),
        ];
        foreach (self::FIELDS as $f) {
            $out[$f] = $this->ready() ? ($f === 'date_of_birth' ? $staff->date_of_birth?->toDateString() : $staff->{$f}) : null;
        }
        $out['family_members'] = $out['family_members'] ?? [];

        return $out;
    }

    /** @param  array<string, mixed>  $data */
    public function save(Staff $staff, array $data): Staff
    {
        $this->mustBeReady();
        $payload = array_intersect_key($data, array_flip(array_merge(self::FIELDS, ['nid'])));
        if (array_key_exists('family_members', $payload)) {
            $payload['family_members'] = array_values($payload['family_members'] ?? []);
        }
        $staff->update($payload + ['kyc_updated_at' => now()]);

        return $staff->fresh();
    }

    /** @param  list<UploadedFile>  $files */
    public function attach(Staff $staff, string $type, array $files, int $by): void
    {
        $this->mustBeReady();
        foreach ($files as $file) {
            StaffDocument::create([
                'staff_id' => $staff->id,
                'type' => $type,
                'path' => $file->store('staff/'.$staff->id, 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'uploaded_by' => $by,
            ]);
        }
    }

    public function remove(StaffDocument $document): void
    {
        Storage::disk('local')->delete($document->path);
        $document->delete();
    }

    private function mustBeReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages(['kyc' => 'ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট করার পর KYC সেভ করা যাবে।']);
        }
    }
}
