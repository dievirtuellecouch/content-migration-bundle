<?php

namespace Pdir\ContentMigrationBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Pdir\ContentMigrationBundle\Handler\PageExportHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\ParameterBag;

#[AsCommand('app:export-forms')]
class ExportFormsCommand extends Command
{
    public function __construct(
        private ContaoFramework $framework,
        private LoggerInterface $logger,
        private PageExportHandler $exportHandler,
    ) {

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('excludeFormIds', InputArgument::OPTIONAL, 'Comma-separated list of form ids which should be excluded')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        $excludeFormIds = $input->getArgument('excludeFormIds');
        if (!empty($excludeFormIds)) {
            $excludeFormIds = \array_map(fn ($part) => (int) trim($part), \explode(',', $excludeFormIds));
        }

        $config = new ParameterBag([
            'exportName' => 'forms',
            'excludeFormIds' => $excludeFormIds,
        ]);

        $this->exportHandler->exportForms($config);

        return Command::SUCCESS;
    }
}
