<?php

namespace Pdir\ContentMigrationBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Pdir\ContentMigrationBundle\Handler\PageImportHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\ParameterBag;

#[AsCommand('app:import-pages')]
class ImportCommand extends Command
{
    public function __construct(
        private ContaoFramework $framework,
        private LoggerInterface $logger,
        private PageImportHandler $importHandler,
    ) {

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('importPath', InputArgument::REQUIRED, 'The path of the folder container all .model files; relative to the "contentMigration" directory in "files" folder.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        $importPath = $input->getArgument('importPath');

        $config = new ParameterBag([
            'import_name' => \sprintf('files/contentMigration/%s', $importPath),
        ]);

        $this->importHandler->processForm($config);

        return Command::SUCCESS;
    }
}
