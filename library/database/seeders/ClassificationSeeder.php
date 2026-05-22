<?php

namespace Database\Seeders;

use App\Domain\Category\Models\Classification;
use Illuminate\Database\Seeder;

class ClassificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Classification::insert([
            ['code' => '000', 'name' => 'Generalities (Computers, encyclopedias, journalism)'],
            ['code' => '100', 'name' => 'Philosophy & Psychology'],
            ['code' => '200', 'name' => 'Religion'],
            ['code' => '300', 'name' => 'Social Sciences (Law, education, sociology)'],
            ['code' => '400', 'name' => 'Language (Grammar, dictionaries)'],
            ['code' => '500', 'name' => 'Science (Mathematics, physics, biology)'],
            ['code' => '600', 'name' => 'Technology (Medicine, engineering, applied sciences)'],
            ['code' => '700', 'name' => 'Arts (Fine arts, music, sports)'],
            ['code' => '800', 'name' => 'Literature & Rhetoric (Poetry, fiction, plays)'],
            ['code' => '900', 'name' => 'History & Geography'],
        ]);
    }
}
