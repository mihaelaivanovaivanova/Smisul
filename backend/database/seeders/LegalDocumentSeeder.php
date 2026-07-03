<?php

namespace Database\Seeders;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds the current (v1.0) version of every legal document type checkout
 * requires acceptance of. Content is placeholder copy — swap in real legal
 * text before going live. Publishing a new version later means inserting a
 * new row with a bumped version and is_current=true, then flipping this
 * one to is_current=false — never editing an already-accepted row (see
 * LegalDocumentService).
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'type' => LegalDocumentType::TermsOfService,
                'title' => 'Общи условия',
                'content' => 'Placeholder: общите условия за пазаруване в Smisul.',
            ],
            [
                'type' => LegalDocumentType::PrivacyPolicy,
                'title' => 'Политика за поверителност',
                'content' => 'Placeholder: политиката за поверителност на Smisul.',
            ],
            [
                'type' => LegalDocumentType::GdprPolicy,
                'title' => 'Политика за защита на личните данни (GDPR)',
                'content' => 'Placeholder: политиката за обработка на лични данни на Smisul.',
            ],
            [
                'type' => LegalDocumentType::RightOfWithdrawal,
                'title' => 'Право на отказ',
                'content' => 'Placeholder: информация за правото на отказ от поръчка.',
            ],
            [
                'type' => LegalDocumentType::CookiePolicy,
                'title' => 'Политика за бисквитките',
                'content' => 'Placeholder: политиката за бисквитки на Smisul.',
            ],
        ];

        foreach ($documents as $document) {
            LegalDocument::updateOrCreate(
                ['type' => $document['type'], 'version' => '1.0'],
                [
                    'title' => $document['title'],
                    'content' => $document['content'],
                    'is_current' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
