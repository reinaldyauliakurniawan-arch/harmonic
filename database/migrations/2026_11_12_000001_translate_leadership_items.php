<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // The starter titles as first seeded, in the order of config/assessment.php, so seeded copies can be found and replaced.
    private const OLD_TITLES = [
        'Mengenal dan proaktif mengejar benchmark yang jelas (mastery level dibidangnya)',
        'Target jadi tanggung jawab pribadi, bukan nunggu disuruh dulu untuk kejer target',
        'Menghasilkan output sesuai kuantitas, kualitas, dan deadline yang sudah ditetapkan secara konsisten.',
        'Proactive Reporting',
        'Follow existing system and rules',
        'Membangun sistem kerja pribadi yang membantu perusahaan',
        'Merasa berhasil karena tim perform, bukan karena bisa mengerjakan tugas teknis tim.',
        'Menganggap pekerjaan manajerial/sistemik sama bernilainya dengan pekerjaan teknis.',
        'Job Design & Delegation - mendelegasikan tugas ke orang yang sesuai value, paham, mau, dan mampu.',
        'Selecting and deselecting team members',
        'Performance Monitoring',
        'Having Tough Conversations',
        'Building Team Engagement (psychological safety)',
        'Membantu tim berkembang mandiri melalui pertanyaan dan feedback (empowering and coaching people)',
        'Basic Budgeting',
        'Membangun sistem kerja / workflow tim yang memudahkan semua orang',
        'Proaktif menginformasikan progress dan hambatan ke atasan, serta mengkoordinasikan kebutuhan tim',
        'Menilai bawahan dari seberapa baik mereka memimpin, bukan cuma dari achieve target',
        'Bertindak berdasarkan informasi terbaik yang tersedia, tidak menunggu kesempurnaan.',
        'Assessing Leadership Performance',
        'Organizational Design - mendesain struktur unit yang jelas',
        'Selecting and developing leaders of others',
        'Translating strategy into operating plans',
        'Leading across the organization',
        'Fight for the business, bukan untuk fungsi sendiri',
        'Think like CEO at function level',
        'Functional Strategy Development',
        'Talent management (function level)',
        'Functional KPIs & Measurement',
        'Design Function structure',
        'Creating Functional System & Rhythm',
        'Managing function budget',
        'Bertanggung jawab atas profitability bisnis',
        'Pemilik, bukan operator (bisnis jalan autopilot beneran)',
        'Bertanggung jawab atas kesehatan dan keberlangsungan organisasi',
        'Building, executing, and delivering the business model and strategy.',
        'Integrating functions through business system and rhythm',
        'Managing complexity',
        'Data-driven decision making',
        'Rahmatan lil alamin - dampak perusahaan melampaui profit',
        'Memastikan perusahaan bertahan dan tumbuh puluhan tahun, bahkan tanpa kehadiran pemimpin',
        'Valuing different business models',
        'Visionary thinking & enterprise strategy',
        'Executive team building & succession',
        'Board & external stakeholder relations',
        'Capital allocation across business units',
        'Culture building & social architecture',
    ];

    public function up(): void
    {
        $english = collect(config('assessment.leadership'))->flatMap(fn ($type) => $type['items'])->values();
        $index = array_flip(self::OLD_TITLES);

        // Only seeded items carry levels, and the app cannot edit an item's text, so a seeded title still means an untouched copy.
        DB::table('assessment_items')->whereNotNull('levels')->whereIn('text', self::OLD_TITLES)->orderBy('id')
            ->each(function ($row) use ($english, $index) {
                // MySQL matched case-insensitively; an English title like "Proactive reporting" is already done.
                if (! isset($index[$row->text])) {
                    return;
                }
                [$text, $levels] = $english[$index[$row->text]];
                DB::table('assessment_items')->where('id', $row->id)->update(['text' => $text, 'levels' => json_encode($levels)]);
            });
    }

    public function down(): void
    {
        // Answers are stored by item id, so leaving the English text in place loses nothing.
    }
};
