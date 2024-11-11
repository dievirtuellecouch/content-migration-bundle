<?php

declare(strict_types=1);

/*
 * Content migration bundle for Contao Open Source CMS
 *
 * Copyright (c) 2022 pdir / digital agentur // pdir GmbH
 *
 * @package    content-migration-bundle
 * @link       https://pdir.de
 * @license    LGPL-3.0+
 * @author     Mathias Arzberger <develop@pdir.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pdir\ContentMigrationBundle\Handler;

use Contao\ArticleModel;
use Contao\BackendTemplate;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Environment;
use Contao\File;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\FormModel;
use Contao\FormFieldModel;
use Symfony\Component\HttpFoundation\ParameterBag;
use Contao\System;
use Contao\TextField;
use Pdir\ContentMigrationBundle\Exporter\ModelExporter;

class PageExportHandler
{
    public function __construct(
        private ContaoFramework $framework
    ) {
    }

    /**
     * Process the form.
     *
     * @codeCoverageIgnore
     *
     * @throws \Exception
     */
    public function processForm(ParameterBag $parameters): void
    {
        $userFolder = ModelExporter::getCurrentUserFolder();

        $folder = $parameters->get('exportName');
        $userFolder .= '/'.('' !== $folder ? $folder : uniqid());

        $pageCounter = 0;
        $articleCounter = 0;
        $elementCounter = 0;
        $pages = null;

        $exportType = $parameters->get('type');
        $pageId = $parameters->get('pageId') ?? null;

        switch ($exportType) {
            case 'full':
                try {
                    $pages = $this->getPages($pageId);
                } catch (ExportException $e) {
                    echo $e->getMessage();
                }
                break;

            case 'page':
                $pages = PageModel::findById($pageId);
                break;

            case 'content':
                echo 'Export type not available yet.';
                break;
        }

        if (null !== $pages) {
            foreach ($pages as $page) {
                $ids = [];
                $ids[] = 'p'.$this->withLeadingZeroes((string)$page->pid);
                $ids[] = 'id'.$this->withLeadingZeroes((string)$page->id);

                // write page model
                $this->saveSerializeFile(
                    $userFolder,
                    $ids,
                    $page->row(),
                    'page'
                );

                // write article model
                /** @var ArticleModel $articleModel */
                $articleModel = ArticleModel::findByPid([$page->id]);

                if (null !== $articleModel) {
                    foreach ($articleModel as $article) {
                        $this->saveSerializeFile(
                            $userFolder,
                            ['id'.$this->withLeadingZeroes((string)$article->id), 'pid'.$this->withLeadingZeroes((string)$article->pid)],
                            $article->row(),
                            'article'
                        );

                        /** @var ContentModel $contentModel */
                        $contentModel = ContentModel::findByPid([$article->id]);

                        if (null !== $contentModel) {
                            foreach ($contentModel as $content) {
                                $this->saveSerializeFile(
                                    $userFolder,
                                    ['pid'.$this->withLeadingZeroes((string)$article->pid), 'id'.$this->withLeadingZeroes((string)$content->id), ($content->ptable ?: 'null')],
                                    $content->row(),
                                    'content'
                                );

                                ++$elementCounter;
                            }
                        }

                        /** @var ModuleModel $moduleModel */
                        //$moduleModel = ModuleModel::findByPid([$article->id]);

                        /** @var NewsArchiveModel $newsArchiveModel */
                        //$newsArchiveModel = NewsArchiveModel::findByPid([$article->id]);

                        /** @var NewsModel $newsModel */
                        //$newsModel = NewsModel::findByPid([$article->id]);

                        /** @var CalendarModel $calendarModel */
                        //$calendarModel = CalendarModel::findByPid([$article->id]);

                        /** @var CalendarEventsModel $calendarEventsModel */
                        //$calendarEventsModel = CalendarEventsModel::findByPid([$article->id]);

                        ++$articleCounter;
                    }
                }
                // write content model

                ++$pageCounter;
            }
        }
    }

    public function exportForms(ParameterBag $parameters): void
    {
        $userFolder = ModelExporter::getCurrentUserFolder();

        $folder = $parameters->get('exportName');
        $userFolder .= '/'.('' !== $folder ? $folder : uniqid());

        $excludeFormIds = $parameters->get('excludeFormIds') ?? [];

        $forms = FormModel::findAll();

        foreach ($forms as $form) {
            if (\in_array($form->id, $excludeFormIds)) {
                continue;
            }

            $this->saveSerializeFile(
                $userFolder,
                ['id' . $this->withLeadingZeroes((string) $form->id)],
                $form->row(),
                'form'
            );

            $formFields = FormFieldModel::findByPid($form->id);

            foreach ($formFields as $formField) {
                $this->saveSerializeFile(
                    $userFolder,
                    ['pid' . $this->withLeadingZeroes((string) $form->id), 'id' . $this->withLeadingZeroes((string) $formField->id)],
                    $formField->row(),
                    'formfield'
                );
            }
        }
    }

    protected function getPages($page = null)
    {
        if (null === $page) {
            /** @var PageModel $pageModel */
            $pageModel = $this->framework->getAdapter(PageModel::class);

            return $pageModel->findAll();
        }

        return $this->getChildPages($page);
    }

    protected function getChildPages($id)
    {
        $objDatabase = Database::getInstance();
        $arrPages = $objDatabase->getChildRecords($id, 'tl_page');

        // add selected page
        array_unshift($arrPages, $id);

        if (\is_array($arrPages) && 0 < \count($arrPages)) {
            $pageModel = $this->framework->getAdapter(PageModel::class);
            $pages = $pageModel->findMultipleByIds($arrPages);

            if (null !== $pages) {
                return $pages;
            }
        }
    }

    /**
     * Generate number with leading zeroes.
     */
    protected function withLeadingZeroes(string $number): string
    {
        return sprintf('%08d', $number);
    }

    /**
     * @throws \Exception
     */
    protected function saveSerializeFile($folder, $parts, $data, $type): void
    {
        $filename = implode('-', $parts);
        $file = new File($folder.\DIRECTORY_SEPARATOR.$type.'.'.$filename.ModelExporter::$fileExt);
        $file->write(serialize($data));
        $file->close();
    }
}
