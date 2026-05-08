<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE kyc_documents MODIFY doc_type ENUM(
                'id_front',
                'id_back',
                'selfie',
                'business_license',
                'address_proof',
                'nid_front',
                'nid_back',
                'nid_selfie',
                'license_front',
                'license_back',
                'license_selfie',
                'passport_page',
                'passport_selfie'
            ) NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE kyc_documents MODIFY doc_type ENUM(
                'id_front',
                'id_back',
                'selfie',
                'business_license',
                'address_proof'
            ) NOT NULL"
        );
    }
};
