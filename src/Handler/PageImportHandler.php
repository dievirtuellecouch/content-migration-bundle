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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FormModel;
use Contao\FormFieldModel;
use Contao\FilesModel;
use Contao\Message;
use Contao\PageModel;
use Contao\System;
use Model\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Pdir\ContentMigrationBundle\Exporter\ModelExporter;
// use Symfony\Component\HttpFoundation\Request;
// use Symfony\Component\HttpFoundation\RequestStack;
// use Contao\CoreBundle\Controller\AbstractBackendController;
use Psr\Log\LoggerInterface;

class PageImportHandler
{
    private ContaoFramework $framework;

    // private RequestStack $requestStack;

    private array $replacementIds;

    /**
     * ExportController constructor.
     */
    public function __construct(ContaoFramework $framework, LoggerInterface $logger)
    {
        $this->framework = $framework;
        // $this->requestStack = $requestStack;
        $this->logger = $logger;
    }

    /**
     * Run the controller.
     *
     * @codeCoverageIgnore
     */
    // public function run(): string
    // {
    //     $formId = 'tl_page_import';

    //     $request = $this->requestStack->getCurrentRequest();

    //     if ($request->request->get('FORM_SUBMIT') === $formId) {
    //         $this->processForm($request);
    //     }

    //     return $this->getTemplate($formId)->parse();
    // }

    /**
     * Process the form.
     *
     * @codeCoverageIgnore
     */
    public function processForm(ParameterBag $parameters): void
    {
        $userFolder = $parameters->get('import_name');

        try {
            // load data from files
            $filesModel = $this->getModelFiles($userFolder);

            // run imports
            $pageCounter = $this->importPages($filesModel);
            $articleCounter = $this->importArticles($filesModel);
            $contentCounter = $this->importContent($filesModel);

            $this->importForms($filesModel);
        } catch (ImportException $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Get the template.
     *
     * @codeCoverageIgnore
     */
    // protected function getTemplate(string $formId): BackendTemplate
    // {
    //     /**
    //      * @var Environment
    //      * @var Message     $message
    //      * @var System      $system
    //      */
    //     $environment = $this->framework->getAdapter(Environment::class);
    //     $message = $this->framework->getAdapter(Message::class);
    //     $system = $this->framework->getAdapter(System::class);

    //     $GLOBALS['TL_CSS'][] = 'bundles/pdircontentmigration/css/backend.css';

    //     $template = new BackendTemplate('be_page_import');
    //     $template->currentUserFolder = ModelExporter::getCurrentUserFolder();
    //     $template->backUrl = $system->getReferer();
    //     $template->action = $environment->get('request');
    //     $template->formId = $formId;
    //     $template->folderOptions = $this->generateFolderOptions();
    //     $template->typeOptions = $this->generateTypeOptions();
    //     $template->message = $message->generate();

    //     return $template;
    // }

    /**
     * Generate the import type options.
     *
     * @codeCoverageIgnore
     */
    // protected function generateTypeOptions(): array
    // {
    //     $options = [];

    //     foreach ($GLOBALS['TL_LANG']['tl_page']['import_typeRef'] as $alias => &$label) {
    //         $options[$alias] = $GLOBALS['TL_LANG']['tl_page']['import_typeRef'][$alias];
    //     }
    //     unset($label);

    //     return $options;
    // }

    /**
     * Generate the options.
     *
     * @codeCoverageIgnore
     *
     * @throws \Exception
     */
    // protected function generateFolderOptions(): array
    // {
    //     // Sync filesystem to get freshly uploaded folders also
    //     Dbafs::syncFiles();

    //     $objUserDir = FilesModel::findByPath(ModelExporter::getCurrentUserFolder());
    //     $objSubfiles = FilesModel::findByPid($objUserDir->uuid, ['order' => 'name']);

    //     $options = [];

    //     if (null === $objSubfiles) {
    //         return [];
    //     }

    //     foreach ($objSubfiles as $file) {
    //         // use only sub folders
    //         if ('folder' === $file->type) {
    //             $options[$file->path] = $file->name;
    //         }
    //     }

    //     return $options;
    // }

    /**
     * @return FilesModel|Collection|null
     */
    protected function getModelFiles(string $folder)
    {
        /** @var FilesModel $filesModel */
        $filesModel = FilesModel::findByPath($folder);

        /** @var FilesModel $objSubfiles */
        $objSubfiles = FilesModel::findByPid($filesModel->uuid, ['order' => 'name']);

        if (null === $objSubfiles) {
            return null;
        }

        return $objSubfiles;
    }

    /**
     * @throws \Exception
     */
    protected function importPages($filesModel): int
    {
        $pageCounter = 0;

        foreach ($filesModel as $fileModel) {
            if ('file' === $fileModel->type) {
                if (0 === strpos($fileModel->name, 'page')) {
                    $modelArr = $this->getModelFileContent($fileModel->path);

                    $pageModel = new PageModel();

                    if (!$modelArr['title']) {
                        continue;
                    }

                    $lastId = null;

                    foreach ($modelArr as $key => $value) {
                        // prevent setting id
                        if ('id' === $key) {
                            $lastId = $value;
                            continue;
                        }

                        $pageModel->{$key} = $value;
                    }

                    if ($pageCounter == 0) {
                        $pageModel->pid = 1;
                    }
                    else {
                        $pageModel->pid = 100000;
                    }

                    // write model to db
                    $pageModel->save();

                    $this->replacementIds['pagePid'.$lastId] = $pageModel->id;
                    ++$pageCounter;
                }
            }
        }

        foreach ($filesModel as $fileModel) {
            if ('file' === $fileModel->type) {
                if (0 === strpos($fileModel->name, 'page')) {
                    $modelArr = $this->getModelFileContent($fileModel->path);

                    $oldId = $this->replacementIds['pagePid' . $modelArr['id']];
                    $pageModel = PageModel::findByPk($oldId);

                    if ($pageModel == null) {
                        continue;
                    }

                    try {
                        $originalPageId = 'pagePid' . $modelArr['pid'];

                        if (!\array_key_exists($originalPageId, $this->replacementIds)) {
                            continue;
                        }

                        $newPid = $this->replacementIds[$originalPageId];
                    }
                    catch(Exception $exception) {
                        continue;
                    }

                    if ($newPid === null) {
                        continue;
                    }

                    $pageModel->pid = $newPid;

                    $pageModel->save();
                }
            }
        }

        return $pageCounter;
    }

    /**
     * @throws \Exception
     */
    protected function importArticles($filesModel): int
    {
        $articleCounter = 0;

        foreach ($filesModel as $fileModel) {
            if ('file' === $fileModel->type) {
                if (0 === strpos($fileModel->name, 'article')) {
                    $modelArr = $this->getModelFileContent($fileModel->path);
                    $articleModel = new ArticleModel();

                    $lastId = null;

                    foreach ($modelArr as $key => $value) {
                        // prevent setting id
                        if ('id' === $key) {
                            $lastId = $value;
                            continue;
                        }

                        if ('pid' === $key) {
                            $articleModel->pid = $this->replacementIds['pagePid'.$value];
                            continue;
                        }

                        $articleModel->{$key} = $value;
                    }
                    $articleModel->save();
                    $this->replacementIds['articlePid'.$lastId] = $articleModel->id;
                    ++$articleCounter;
                }
            }
        }

        return $articleCounter;
    }

    /**
     * @throws \Exception
     */
    protected function importContent($filesModel): int
    {
        $contentCounter = 0;

        $contentReplacementIds = [];

        foreach ($filesModel as $fileModel) {
            if ('file' === $fileModel->type) {
                if (0 === strpos($fileModel->name, 'content')) {
                    $modelArr = $this->getModelFileContent($fileModel->path);
                    $contentModel = new ContentModel();

                    $lastId = null;

                    foreach ($modelArr as $key => $value) {
                        // prevent setting id
                        if ('id' === $key) {
                            $lastId = $value;
                            continue;
                        }

                        if ('pid' === $key) {
                            $contentModel->pid = $this->replacementIds['articlePid'.$value];
                            continue;
                        }

                        $contentModel->{$key} = $value;
                    }

                    $contentModel->save();
                    $contentReplacementIds['contentId'.$lastId] = $contentModel->id;
                    ++$contentCounter;
                }
            }
        }

        // Map old content element and article references (content type "alias" and "article")
        // to the ids of the imported elements.
        foreach ($filesModel as $fileModel) {
            if ('file' !== $fileModel->type) {
                continue;
            }

            if (0 !== strpos($fileModel->name, 'content')) {
                continue;
            }

            $modelArr = $this->getModelFileContent($fileModel->path);
            if (!\array_key_exists('contentId' . $modelArr['id'], $contentReplacementIds)) {
                echo \sprintf('Remapping: Could not find original content with ID %s.', $modelArr['id']);
                continue;
            }

            $newId = $contentReplacementIds['contentId' . $modelArr['id']];
            $contentModel = ContentModel::findByPk($newId);

            if ($contentModel === null) {
                echo \sprintf('Remapping: Could not find content model with ID %s.', $newId);
                continue;
            }

            if ($contentModel->type == 'alias') {
                $contentModel->cteAlias = $contentReplacementIds['contentId'.$contentModel->cteAlias];
                $contentModel->save();
                continue;
            }

            if ($contentModel->type == 'article') {
                $contentModel->articleAlias = $this->replacementIds['articlePid'.$contentModel->articleAlias];
                $contentModel->save();
                continue;
            }
        }

        return $contentCounter;
    }

    protected function importForms($filesModel): void
    {
        $contentCounter = 0;

        $formReplacementIds = [];

        foreach ($filesModel as $fileModel) {
            if ('file' !== $fileModel->type) {
                continue;
            }

            if (0 !== strpos($fileModel->name, 'form.')) {
                continue;
            }

            $modelArr = $this->getModelFileContent($fileModel->path);
            $formModel = new FormModel();

            foreach ($modelArr as $key => $value) {
                // prevent setting id
                if ('id' === $key) {
                    continue;
                }

                $formModel->{$key} = $value;
            }

            $formModel->save();

            $formReplacementIds[$modelArr['id']] = $formModel->id;
        }

        foreach ($filesModel as $fileModel) {
            if ('file' !== $fileModel->type) {
                continue;
            }

            if (0 !== strpos($fileModel->name, 'formfield.')) {
                continue;
            }

            $modelArr = $this->getModelFileContent($fileModel->path);
            $formFieldModel = new FormFieldModel();

            foreach ($modelArr as $key => $value) {
                // prevent setting id
                if ('id' === $key) {
                    continue;
                }

                if ('pid' === $key) {
                    $formFieldModel->pid = $formReplacementIds[$value];
                    continue;
                }

                $formFieldModel->{$key} = $value;
            }

            $formFieldModel->save();

            // $formReplacementIds[$modelArr['id']] = $formFieldModel->id;
        }

        // Replace forms inserts in content
        foreach ($formReplacementIds as $originalId => $newId) {
            $contentElements = ContentModel::findByForm($originalId);

            if ($contentElements === null) {
                continue;
            }

            foreach ($contentElements as $contentElement) {
                $contentElement->form = $newId;
                $contentElement->save();
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function getModelFileContent(string $path): array
    {
        $file = new File($path);
        $content = unserialize($file->getContent());

        if (\is_array($content)) {
            return $content;
        }
    }
}
