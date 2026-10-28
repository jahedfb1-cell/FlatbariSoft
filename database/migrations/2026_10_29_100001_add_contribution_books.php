<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collections are kept in separate books, each with its own heads, targets and ledgers: the construction
 * cost, the flat itself, and parking sales. A flat owner has one ledger per book. The single default target
 * that used to live in contribution_settings becomes the construction book's own default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_books', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_bn');
            $table->string('name_en');
            // 0 = no target decided: such a book shows what was collected but never a "due".
            $table->decimal('default_target', 15, 4)->default(0);
            // Ledger account that new receipts in this book are credited to.
            $table->string('credit_account_code', 10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('contribution_heads', function (Blueprint $table) {
            $table->foreignId('contribution_book_id')->nullable()->after('id')->constrained('contribution_books')->restrictOnDelete();
        });

        Schema::table('flat_contribution_targets', function (Blueprint $table) {
            $table->foreignId('contribution_book_id')->nullable()->after('flat_id')->constrained('contribution_books')->restrictOnDelete();
            // the composite index also serves the flat_id foreign key, so the old single-column one can go
            $table->unique(['flat_id', 'contribution_book_id']);
            $table->dropUnique(['flat_id']);
        });

        $default = Schema::hasTable('contribution_settings') ? (DB::table('contribution_settings')->value('default_target') ?? 0) : 0;
        $now = now();
        $ids = [];
        foreach ([
            ['CONSTRUCTION', 'নির্মাণ ব্যয়', 'Construction cost', $default, '3200', 1],
            ['FLAT', 'ফ্ল্যাট', 'Flat', 0, '3400', 2],
            ['PARKING_SALE', 'পার্কিং বিক্রয়', 'Parking sale', 0, '3500', 3],
        ] as [$code, $bn, $en, $target, $account, $sort]) {
            $ids[$code] = DB::table('contribution_books')->insertGetId([
                'code' => $code, 'name_bn' => $bn, 'name_en' => $en, 'default_target' => $target,
                'credit_account_code' => $account, 'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // what existed so far is all construction cost
        DB::table('contribution_heads')->whereNull('contribution_book_id')->update(['contribution_book_id' => $ids['CONSTRUCTION']]);
        DB::table('flat_contribution_targets')->whereNull('contribution_book_id')->update(['contribution_book_id' => $ids['CONSTRUCTION']]);

        foreach ([['FLAT_PRICE', 'ফ্ল্যাট মূল্য', 'Flat price', 'FLAT'], ['PARKING_PRICE', 'পার্কিং মূল্য', 'Parking price', 'PARKING_SALE']] as $i => [$code, $bn, $en, $book]) {
            DB::table('contribution_heads')->insertOrIgnore([
                'contribution_book_id' => $ids[$book], 'code' => $code, 'name_bn' => $bn, 'name_en' => $en,
                'counts_toward_target' => true, 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::dropIfExists('contribution_settings');
    }

    public function down(): void
    {
        // Not reversible without losing the per-book targets; the books are additive, so leave them in place.
    }
};
