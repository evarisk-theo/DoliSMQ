<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 * \file    core/modules/dolismq/dolismqdocuments/controldocument/doc_controldocument_odt.modules.php
 * \ingroup dolismq
 * \brief   File of class to build ODT control document.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../../../../../saturne/class/saturnesignature.class.php';
require_once __DIR__ . '/../../../../../../saturne/core/modules/saturne/modules_saturne.php';

// Load DoliSMQ libraries.
require_once __DIR__ . '/mod_controldocument_standard.php';

require_once __DIR__ . '/../../../../../class/question.class.php';
require_once __DIR__ . '/../../../../../class/sheet.class.php';
require_once __DIR__ . '/../../../../../class/answer.class.php';

/**
 * Class to build documents using ODF templates generator.
 */
class doc_controldocument_odt extends SaturneDocumentModel
{
    /**
     * @var array Minimum version of PHP required by module.
     * e.g.: PHP ≥ 5.5 = array(5, 5)
     */
    public array $phpmin = [7, 4];

    /**
     * @var string Dolibarr version of the loaded document.
     */
    public string $version = 'dolibarr';

    /**
     * @var string Module.
     */
    public string $module = 'dolismq';

    /**
     * @var string Document type.
     */
    public string $document_type = 'controldocument';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->document_type);
    }

    /**
     * Return description of a module.
     *
     * @param  Translate $langs Lang object to use for output.
     * @return string           Description.
     */
    public function info(Translate $langs): string
    {
        return parent::info($langs);
    }

    /**
     * Fill all odt tags for segments lines.
     *
     * @param  Odf       $odfHandler  Object builder odf library.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  array     $moreParam   More param (Object/user/etc).
     *
     * @return int                    1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function fillTagsLines(Odf $odfHandler, Translate $outputLangs, array $moreParam): int
    {
        global $conf, $moduleNameLowerCase, $langs;

        $object = $moreParam['object'];

        // Replace tags of lines.
        try {
            // Get attendants role controller.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('controllers');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    $signatory        = new SaturneSignature($this->db, $this->module, $object->element);
                    $signatoriesArray = $signatory->fetchSignatory('Controller', $object->id, $object->element);
                    if (!empty($signatoriesArray) && is_array($signatoriesArray)) {
                        $tempDir = $conf->$moduleNameLowerCase->multidir_output[$object->entity ?? 1] . '/temp/';
                        foreach ($signatoriesArray as $objectSignatory) {
                            $tmpArray['controller_firstname']      = $objectSignatory->firstname;
                            $tmpArray['controller_lastname']       = strtoupper($objectSignatory->lastname);
                            $tmpArray['controller_signature_date'] = dol_print_date($objectSignatory->signature_date, 'dayhour', 'tzuser');
                            if (dol_strlen($objectSignatory->signature) > 0 && $objectSignatory->signature != $langs->transnoentities('FileGenerated')) {
                                if ($moreParam['specimen'] == 0 || ($moreParam['specimen'] == 1 && $conf->global->DOLISMQ_SHOW_SIGNATURE_SPECIMEN == 1)) {
                                    $encodedImage = explode(',', $objectSignatory->signature)[1];
                                    $decodedImage = base64_decode($encodedImage);
                                    file_put_contents($tempDir . 'signature' . $objectSignatory->id . '.png', $decodedImage);
                                    $tmpArray['controller_signature'] = $tempDir . 'signature' . $objectSignatory->id . '.png';
                                } else {
                                    $tmpArray['controller_signature'] = '';
                                }
                            } else {
                                $tmpArray['controller_signature'] = '';

                            }
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                            dol_delete_file($tempDir . 'signature' . $objectSignatory->id . '.png');
                        }
                    } else {
                        $tmpArray['controller_firstname']      = '';
                        $tmpArray['controller_lastname']       = '';
                        $tmpArray['controller_signature_date'] = '';
                        $tmpArray['controller_signature']      = '';
                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                    $odfHandler->mergeSegment($listLines);
                }
            }

            $moreParam['excludeAttendantsRole'] = ['Controller'];
            $this->setAttendantsSegment($odfHandler, $outputLangs, $moreParam);

            // Get questions.
            $photoArray       = [];
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('questions');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    $object->fetchObjectLinked($object->fk_sheet, 'dolismq_sheet');
                    $questionIds = $object->linkedObjectsIds;
                    if (is_array($questionIds['dolismq_question']) && !empty($questionIds['dolismq_question'])) {
                        $controldet = new ControlLine($this->db);
                        $question   = new Question($this->db);
                        $answer     = new Answer($this->db);
                        foreach ($questionIds['dolismq_question'] as $questionId) {
                            $question->fetch($questionId);

                            $controldets = $controldet->fetchFromParentWithQuestion($object->id, $questionId);

                            $tmpArray['ref']         = $question->ref;
                            $tmpArray['label']       = $question->label;
                            $tmpArray['description'] = strip_tags($question->description);

                            if (is_array($controldets) && !empty($controldets)) {
                                $questionAnswerLine     = array_shift($controldets);
                                $tmpArray['ref_answer'] = $questionAnswerLine->ref;
                                $tmpArray['comment']    = dol_htmlentitiesbr_decode(strip_tags($questionAnswerLine->comment, '<br>'));

                                $answerResult = $questionAnswerLine->answer;

                                $question->fetch($questionAnswerLine->fk_question);
                                $answerList = $answer->fetchAll('ASC', 'position', '', '', ['fk_question' => $questionAnswerLine->fk_question]);

                                $answersArray = [];
                                if (is_array($answerList) && !empty($answerList)) {
                                    foreach ($answerList as $answerSingle) {
                                        $answersArray[$answerSingle->position] = $answerSingle->value;
                                    }
                                }

                                switch ($question->type) {
                                    case 'OkKo' :
                                    case 'OkKoToFixNonApplicable' :
                                    case 'UniqueChoice' :
                                        $tmpArray['answer'] = $answersArray[$answerResult];
                                        break;
                                    case 'Text' :
                                    case 'Range' :
                                        $tmpArray['answer'] = $answerResult;
                                        break;
                                    case 'Percentage' :
                                        $tmpArray['answer'] = $answerResult . ' %';
                                        break;
                                    case 'MultipleChoices' :
                                        $answers = explode(',', $answerResult);
                                        $tmpArray['answer'] = '';
                                        foreach ($answers as $answerId) {
                                            $tmpArray['answer'] .= $answersArray[$answerId] . ', ';
                                        }
                                        $tmpArray['answer'] = rtrim($tmpArray['answer'], ', ');
                                        break;
                                    default:
                                        $tmpArray['answer'] = '';
                                }

                                $path     = $conf->dolismq->multidir_output[$conf->entity] . '/control/' . $object->ref . '/answer_photo/' . $question->ref;
                                $fileList = dol_dir_list($path, 'files');
                                // Fill an array with photo path and ref of the answer for next loop.
                                if (is_array($fileList) && !empty($fileList)) {
                                    foreach ($fileList as $singleFile) {
                                        $fileSmall          = saturne_get_thumb_name($singleFile['name']);
                                        $image              = $path . '/thumbs/' . $fileSmall;
                                        $photoArray[$image] = $questionAnswerLine->ref;
                                    }
                                }
                            }
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        }
                        $odfHandler->mergeSegment($listLines);
                    }
                }
            }

            // Get answer photos.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('photos');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            // Loop on previous photos array.
            if ($foundTagForLines) {
                if (is_array($photoArray) && !empty($photoArray)) {
                    foreach ($photoArray as $photoPath => $answerRef) {
                        $fileInfo = preg_split('/thumbs\//', $photoPath);
                        $name     = end($fileInfo);

                        $tmpArray['answer_ref'] = ($previousRef == $answerRef) ? '' : $outputLangs->trans('Ref') . ' : ' . $answerRef;
                        $tmpArray['media_name'] = $name;
                        $tmpArray['photo']      = $photoPath;

                        $previousRef = $answerRef;

                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                } else {
                    $tmpArray['answer_ref'] = ' ';
                    $tmpArray['media_name'] = ' ';
                    $tmpArray['photo']      = ' ';
                    $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                }
                $odfHandler->mergeSegment($listLines);
            }
        } catch (OdfException $e) {
            $this->error = $e->getMessage();
            dol_syslog($this->error, LOG_WARNING);
            return -1;
        }
        return 0;
    }

    /**
     * Function to build a document on disk.
     *
     * @param  SaturneDocuments $objectDocument  Object source to build document.
     * @param  Translate        $outputLangs     Lang object to use for output.
     * @param  string           $srcTemplatePath Full path of source filename for generator using a template file.
     * @param  int              $hideDetails     Do not show line details.
     * @param  int              $hideDesc        Do not show desc.
     * @param  int              $hideRef         Do not show ref.
     * @param  array            $moreParam       More param (Object/user/etc).
     * @return int                               1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function write_file(SaturneDocuments $objectDocument, Translate $outputLangs, string $srcTemplatePath, int $hideDetails = 0, int $hideDesc = 0, int $hideRef = 0, array $moreParam): int
    {
        global $conf;

        $object = $moreParam['object'];

        if (!empty($object->photo)) {
            $path      = $conf->dolismq->multidir_output[$conf->entity] . '/control/' . $object->ref . '/photos';
            $fileSmall = saturne_get_thumb_name($object->photo);
            $image     = $path . '/thumbs/' . $fileSmall;
            $tmpArray['photoDefault'] = $image;
        } else {
            $noPhoto                  = '/public/theme/common/nophoto.png';
            $tmpArray['photoDefault'] = DOL_DOCUMENT_ROOT . $noPhoto;
        }

        $outputLangs->loadLangs(['products', 'bills', 'orders', 'contracts', 'projects', 'companies']);

        $sheet      = new Sheet($this->db);
        $usertmp    = new User($this->db);
        $projecttmp = new Project($this->db);

        $sheet->fetch($object->fk_sheet);
        $usertmp->fetch($object->fk_user_controller);
        $projecttmp->fetch($object->projectid);

        $object->fetchObjectLinked('', '', '', 'dolismq_control');
        foreach ($object->linkedObjectsIds as $key => $linkedObjects) {
            // Special case
            if ($key == 'productbatch') {
                $productlot = new Productlot($this->db);
                $productlot->fetch(array_shift($object->linkedObjectsIds['productbatch']));
                $tmpArray['object_label_ref'] .= (!empty($productlot->batch) ? $outputLangs->transnoentities('Batch') . ' : ' . $productlot->batch . chr(0x0A) : '');
            } elseif (!empty($object->linkedObjects[$key])) {
                $linkedObject = array_values($object->linkedObjects[$key])[0];
                $objectInfo = [
                    'product'      => ['title' => 'Product',    'value' => $linkedObject->ref],
                    'user'         => ['title' => 'User',       'value' => strtoupper($linkedObject->lastname) . ' ' . $linkedObject->firstname],
                    'societe'      => ['title' => 'ThirdParty', 'value' => $linkedObject->name],
                    'contact'      => ['title' => 'Contact',    'value' => strtoupper($linkedObject->lastname) . ' ' . $linkedObject->firstname],
                    'project'      => ['title' => 'Project',    'value' => $linkedObject->ref . ' - ' . $linkedObject->title],
                    'project_task' => ['title' => 'Task',       'value' => $linkedObject->ref . ' - ' . $linkedObject->label],
                    'facture'      => ['title' => 'Bill',       'value' => $linkedObject->ref],
                    'commande'     => ['title' => 'Order',      'value' => $linkedObject->ref],
                    'contrat'      => ['title' => 'Contract',   'value' => $linkedObject->ref],
                    'ticket'       => ['title' => 'Ticket',     'value' => $linkedObject->ref],
                ];
                $tmpArray['object_label_ref'] .= $outputLangs->transnoentities($objectInfo[$key]['title']) . ' : ' . $objectInfo[$key]['value'] . chr(0x0A);
            }
        }

        $tmpArray['control_ref']      = $object->ref;
        $tmpArray['object_label_ref'] = rtrim($tmpArray['object_label_ref'], chr(0x0A));
        $tmpArray['control_date']     = dol_print_date($object->date_creation, 'dayhour', 'tzuser');
        $tmpArray['project_label']    = $projecttmp->ref . ' - ' . $projecttmp->title;
        $tmpArray['sheet_ref']        = $sheet->ref;
        $tmpArray['sheet_label']      = $sheet->label;

        switch ($object->verdict) {
            case 1:
                $tmpArray['verdict'] = 'OK';
                break;
            case 2:
                $tmpArray['verdict'] = 'KO';
                break;
            default:
                $tmpArray['verdict'] = '';
                break;
        }

        $tmpArray['public_note'] = $object->note_public;

        $tmpArray['mycompany_name']    = $conf->global->MAIN_INFO_SOCIETE_NOM;
        $tmpArray['mycompany_address'] = (!empty($conf->global->MAIN_INFO_SOCIETE_ADRESS) ? ' - ' . $conf->global->MAIN_INFO_SOCIETE_ADRESS : '');
        $tmpArray['mycompany_website'] = (!empty($conf->global->MAIN_INFO_SOCIETE_WEBSITE) ? ' - ' . $conf->global->MAIN_INFO_SOCIETE_WEBSITE : '');
        $tmpArray['mycompany_mail']    = (!empty($conf->global->MAIN_INFO_SOCIETE_MAIL) ? ' - ' . $conf->global->MAIN_INFO_SOCIETE_MAIL : '');
        $tmpArray['mycompany_phone']   = (!empty($conf->global->MAIN_INFO_SOCIETE_PHONE) ? ' - ' . $conf->global->MAIN_INFO_SOCIETE_PHONE : '');

        $moreParam['tmparray'] = $tmpArray;

        return parent::write_file($objectDocument, $outputLangs, $srcTemplatePath, $hideDetails, $hideDesc, $hideRef, $moreParam);
    }
}
