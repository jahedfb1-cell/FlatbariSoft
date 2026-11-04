<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** The people and firms the building buys from, created once and picked on every purchase. */
class SupplierService
{
    public function ready(): bool
    {
        return Schema::hasTable('suppliers');
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $kind = null, bool $onlyActive = false): array
    {
        if (! $this->ready()) {
            return [];
        }

        return Supplier::when($kind, fn ($q) => $q->where('kind', strtoupper($kind)))->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get()->map(fn (Supplier $s) => $this->present($s))->all();
    }

    /** @return array<string, mixed> */
    public function present(Supplier $s): array
    {
        return ['id' => $s->id, 'name' => $s->name, 'kind' => $s->kind, 'phone' => $s->phone, 'address' => $s->address, 'notes' => $s->notes, 'is_active' => $s->is_active];
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Supplier
    {
        $this->mustBeReady();
        $this->assertNameFree($data['name'], $data['kind']);

        return Supplier::create($data + ['is_active' => true]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Supplier $supplier, array $data): Supplier
    {
        if (isset($data['name']) || isset($data['kind'])) {
            $this->assertNameFree($data['name'] ?? $supplier->name, $data['kind'] ?? $supplier->kind, $supplier->id);
        }
        $supplier->update($data);

        return $supplier->fresh();
    }

    /**
     * The name a purchase is recorded under: the chosen supplier's own name (so spellings never drift), or the typed name.
     * A supplier of another kind, or one switched off, cannot be picked.
     */
    public function nameFor(?int $supplierId, ?string $typed, string $kind): ?string
    {
        if ($supplierId && $this->ready()) {
            $s = Supplier::find($supplierId);
            if (! $s || ! $s->is_active || ($s->kind !== $kind && $s->kind !== 'OTHER')) {
                throw ValidationException::withMessages(['supplier_id' => 'এই সরবরাহকারী বাছা যাবে না — তালিকা থেকে সক্রিয় সরবরাহকারী বাছুন।']);
            }

            return $s->name;
        }

        return $typed !== null && trim($typed) !== '' ? trim($typed) : null;
    }

    private function assertNameFree(string $name, string $kind, ?int $exceptId = null): void
    {
        $key = SupplierLedgerService::key($name);
        $dup = Supplier::where('kind', $kind)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->get()->first(fn ($s) => SupplierLedgerService::key($s->name) === $key);
        if ($dup) {
            throw ValidationException::withMessages(['name' => 'এই নামে একই ধরনের সরবরাহকারী আগেই আছে।']);
        }
    }

    private function mustBeReady(): void
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages(['name' => 'ডাটাবেস আপগ্রেড বাকি আছে — আপগ্রেড ফাইল ইমপোর্ট করার পর সরবরাহকারী তৈরি করা যাবে।']);
        }
    }
}
