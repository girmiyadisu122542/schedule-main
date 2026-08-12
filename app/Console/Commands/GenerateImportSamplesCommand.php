<?php

namespace App\Console\Commands;

use App\Constants\ImportConstant;
use App\Services\Export\SpreadsheetWriterService;
use App\Support\Import\ColumnMap\AbstractColumnMap;
use App\Support\Import\ColumnMap\BuildingColumnMap;
use App\Support\Import\ColumnMap\CollegeColumnMap;
use App\Support\Import\ColumnMap\CourseColumnMap;
use App\Support\Import\ColumnMap\DepartmentColumnMap;
use App\Support\Import\ColumnMap\InstructorColumnMap;
use App\Support\Import\ColumnMap\ProgramColumnMap;
use App\Support\Import\ColumnMap\RoomColumnMap;
use App\Support\Import\ColumnMap\SectionColumnMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateImportSamplesCommand extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:generate-import-samples';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate the committed worked import samples in Docs/samples/master-data';

    /**
     * Write one .xlsx and one .csv sample per master-data entity.
     *
     * The rows are held here rather than exported from the database on purpose:
     * a sample is a WORKED EXAMPLE a registrar copies, so it has to show new
     * records being added against the seeded parents — not echo back rows that
     * already exist and would fail in create_only mode.
     *
     * Regenerate this whenever a column changes; the header row comes from the
     * column map, so a stale sample shows up as a column count mismatch here
     * rather than as a confusing import failure for a user.
     *
     * @return int
     */
    public function handle(): int {
        // A brand-new database has no tables yet, and a sample referencing rows
        // that do not exist is worse than no sample (CLAUDE §10.11).
        if (!Schema::hasTable('colleges')) {
            consoleError('schedule:generate-import-samples cannot proceed: run migrations first.');

            return self::FAILURE;
        }

        $directory = base_path(ImportConstant::SAMPLE_DIRECTORY);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = app(SpreadsheetWriterService::class);
        $written = 0;

        foreach ($this->samples() as $entity => $sample) {
            /** @var \App\Support\Import\ColumnMap\AbstractColumnMap $map */
            $map = $sample['map'];
            $rows = $sample['rows'];

            $mismatch = $this->findColumnCountMismatch($map, $rows);
            if ($mismatch !== null) {
                consoleError("Sample rows for {$entity} have {$mismatch} cells but the column map declares " . count($map->columns()) . '.');

                return self::FAILURE;
            }

            foreach (ImportConstant::SUPPORTED_FORMATS as $format) {
                $path = $directory . DIRECTORY_SEPARATOR . $entity . '.' . $format;
                file_put_contents($path, $writer->raw($map->headers(), $rows, $format, $map->textColumnIndexes()));
                $written++;
            }

            $this->line("  <info>✓</info> {$entity} (" . count($rows) . ' rows)');
        }

        $this->info("Wrote {$written} sample files to " . realpath($directory));

        return self::SUCCESS;
    }

    /**
     * Guard against a sample that silently drifted from its column map.
     *
     * @param \App\Support\Import\ColumnMap\AbstractColumnMap $map
     * @param array<int, array<int, mixed>> $rows
     *
     * @return int|null the offending row's cell count, or null when all match
     */
    private function findColumnCountMismatch(AbstractColumnMap $map, array $rows): ?int {
        $expected = count($map->columns());

        foreach ($rows as $row) {
            if (count($row) !== $expected) {
                return count($row);
            }
        }

        return null;
    }

    /**
     * The worked rows, per entity.
     *
     * Every foreign key here names a row the seeders create (campuses MAIN/TECH,
     * colleges COET/CNCS/CBE, departments CS/SE/EE/MGT, programs BSC-CS/BSC-SE,
     * academic years 2024/25 and 2025/26, users admin@ / registrar@ / teacher@),
     * so the whole set imports cleanly against a fresh `migrate:fresh --seed`.
     *
     * @return array<string, array{map: \App\Support\Import\ColumnMap\AbstractColumnMap, rows: array<int, array<int, mixed>>}>
     */
    private function samples(): array {
        return [
            'colleges' => [
                'map' => new CollegeColumnMap(),
                'rows' => [
                    ['CHS', 'College of Health Sciences', 'registrar@schedule.com', 'Yes'],
                    ['CSSH', 'College of Social Sciences and Humanities', null, 'Yes'],
                    ['CVM', 'College of Veterinary Medicine', null, 'Yes'],
                    ['CLAW', 'School of Law', 'admin@schedule.com', 'No'],
                ],
            ],

            'buildings' => [
                'map' => new BuildingColumnMap(),
                'rows' => [
                    ['HB', 'Health Block', 'MAIN', 3, 'Yes'],
                    ['LIB', 'Library Building', 'MAIN', 5, 'Yes'],
                    ['WS', 'Engineering Workshop', 'TECH', 1, 'Yes'],
                    ['ANX', 'South Annex', 'SOUTH', 2, 'No'],
                ],
            ],

            'departments' => [
                'map' => new DepartmentColumnMap(),
                'rows' => [
                    ['IS', 'Information Systems', 'COET', 'registrar@schedule.com', 'Yes'],
                    ['CE', 'Civil Engineering', 'COET', null, 'Yes'],
                    ['STAT', 'Statistics', 'CNCS', null, 'Yes'],
                    ['ACCT', 'Accounting and Finance', 'CBE', 'teacher@schedule.com', 'Yes'],
                ],
            ],

            'programs' => [
                'map' => new ProgramColumnMap(),
                'rows' => [
                    ['BSC-IT', 'BSc in Information Technology', 'CS', DEGREE_LEVEL_BACHELOR, 4, 'Yes'],
                    ['MSC-SE', 'MSc in Software Engineering', 'SE', DEGREE_LEVEL_MASTER, 2, 'Yes'],
                    ['PHD-CS', 'PhD in Computer Science', 'CS', DEGREE_LEVEL_PHD, 4, 'Yes'],
                    ['DIP-EE', 'Diploma in Electrical Engineering', 'EE', DEGREE_LEVEL_DIPLOMA, 3, 'Yes'],
                ],
            ],

            'sections' => [
                'map' => new SectionColumnMap(),
                'rows' => [
                    ['BSC-CS', '2025/26', 3, 'C', 42, 'Yes'],
                    ['BSC-CS', '2025/26', 3, 'D', 38, 'Yes'],
                    ['BSC-SE', '2025/26', 2, 'B', 51, 'Yes'],
                    ['BSC-EE', '2025/26', 4, 'A', 33, 'Yes'],
                ],
            ],

            'rooms' => [
                'map' => new RoomColumnMap(),
                'rows' => [
                    ['NB-401', 'Lecture Hall 401', 'NB', ROOM_TYPE_LECTURE_HALL, 4, 80, 40, 'Yes', 'Yes'],
                    ['NB-B02', 'Basement Computer Lab', 'NB', ROOM_TYPE_LAB, -1, 40, null, 'No', 'Yes'],
                    ['AB-101', 'Seminar Room 101', 'AB', ROOM_TYPE_SEMINAR_ROOM, 1, 25, null, 'No', 'Yes'],
                    ['LAB-201', 'Grand Auditorium', 'LAB', ROOM_TYPE_AUDITORIUM, 2, 300, 150, 'Yes', 'Yes'],
                ],
            ],

            'courses' => [
                'map' => new CourseColumnMap(),
                'rows' => [
                    ['CS401', 'Machine Learning', 'CS', COURSE_TYPE_LECTURE_LAB, 4, 6, 3, 3, 0, 2, 'Supervised and unsupervised learning.', 'Yes'],
                    ['CS402', 'Distributed Systems', 'CS', COURSE_TYPE_LECTURE_LAB, 3, 5, 2, 3, 0, 2, 'Consistency, replication and consensus.', 'Yes'],
                    ['SE410', 'Software Requirements Engineering', 'SE', COURSE_TYPE_LECTURE, 3, 3, 3, 0, 0, 1, 'Elicitation, specification and validation.', 'Yes'],
                    ['EE310', 'Power Electronics', 'EE', COURSE_TYPE_LECTURE_LAB, 4, 6, 3, 2, 1, 3, 'Converters, inverters and drives.', 'Yes'],
                ],
            ],

            'instructors' => [
                'map' => new InstructorColumnMap(),
                'rows' => [
                    ['EMP-2001', 'Dr. Tigist Haile', 'CS', 'tigist.haile@schedule.com', '+251911223344', ACADEMIC_RANK_ASSISTANT_PROFESSOR, null, 'Yes', 'Yes', 18, 'Yes'],
                    ['EMP-2002', 'Bereket Tadesse', 'SE', 'bereket.tadesse@schedule.com', '+251911223355', ACADEMIC_RANK_LECTURER, null, 'Yes', 'Yes', 20, 'Yes'],
                    // Only invigilates — the reason one table serves both populations.
                    ['EMP-2003', 'Marta Assefa', 'EE', 'marta.assefa@schedule.com', '+251911223366', ACADEMIC_RANK_TECHNICAL_ASSISTANT, null, 'No', 'Yes', null, 'Yes'],
                    // Teaches but is exempt from invigilation duty.
                    ['EMP-2004', 'Prof. Getachew Worku', 'CS', 'getachew.worku@schedule.com', '+251911223377', ACADEMIC_RANK_PROFESSOR, null, 'Yes', 'No', 12, 'Yes'],
                ],
            ],
        ];
    }
}
