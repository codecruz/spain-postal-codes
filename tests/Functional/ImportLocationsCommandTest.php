<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportLocationsCommandTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private CommandTester $tester;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());
        $this->tester = new CommandTester($application->find('app:locations:import'));
        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function testImportsBundledDataset(): void
    {
        $this->tester->execute([]);

        $this->tester->assertCommandIsSuccessful();
        self::assertGreaterThan(13000, $this->countLocations());
    }

    public function testImportReplacesExistingRowsAndSkipsDuplicates(): void
    {
        $this->loadLocations([['Madrid', 'Old row', '28999']]);

        $file = $this->csv(<<<'CSV'
            provincia;poblacion;cod_postal
            Madrid;Madrid;28001
            Madrid;Madrid;28001
            Sevilla;Sevilla;28002
            CSV);

        $this->tester->execute(['--file' => $file], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->tester->assertCommandIsSuccessful();
        self::assertSame(2, $this->countLocations());

        $display = $this->display();
        self::assertStringContainsString('1 duplicate row(s) skipped', $display);
        self::assertStringContainsString('1 row(s) have a postal code that does not belong to their province', $display);
        self::assertStringContainsString('line 4: 28002 (Sevilla) Sevilla', $display);
    }

    public function testInvalidFileFailsWithoutTouchingTheDatabase(): void
    {
        $this->loadLocations([['Madrid', 'Madrid', '28001']]);

        $file = $this->csv("provincia;poblacion;cod_postal\nMadrid;Alcobendas;28100\nAtlantis;Foo;99999\n");

        self::assertSame(Command::FAILURE, $this->tester->execute(['--file' => $file]));
        self::assertStringContainsString('unknown province "Atlantis"', $this->display());
        self::assertSame(1, $this->countLocations(), 'the transaction must be rolled back');
    }

    /** Command output with line wrapping undone, so assertions do not depend on terminal width. */
    private function display(): string
    {
        return (string) preg_replace('/\s+/', ' ', $this->tester->getDisplay());
    }

    private function csv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'cp_');
        self::assertNotFalse($file);
        file_put_contents($file, $contents."\n");
        $this->files[] = $file;

        return $file;
    }
}
