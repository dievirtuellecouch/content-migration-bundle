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

#[AsCommand('app:export-pages')]
class ExportPagesCommand extends Command
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
            ->addArgument('basePageId', InputArgument::REQUIRED, 'The ID of the base page to be exported.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        $basePageId = $input->getArgument('basePageId');

        $config = new ParameterBag([
            'exportName' => str_replace('/', '_', (string) $basePageId),
            'type' => 'full',
            'pageId' => (int) $basePageId,
        ]);

        $this->exportHandler->processForm($config);

        return Command::SUCCESS;
    }
}
