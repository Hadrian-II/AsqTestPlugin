<?php

use srag\CQRS\Exception\CQRSException;
use srag\DIC\AssessmentTest\DICTrait;
use srag\Plugins\AssessmentTest\ObjectSettings\ObjectSettingsFormGUI;
use srag\Plugins\AssessmentTest\Utils\AssessmentTestTrait;
use srag\asq\AsqGateway;
use srag\asq\Application\Service\AuthoringContextContainer;
use srag\asq\Application\Service\IAuthoringCaller;
use srag\asq\Domain\QuestionDto;
use srag\asq\Infrastructure\Persistence\SimpleStoredAnswer;
use srag\asq\Infrastructure\Persistence\EventStore\QuestionEventStoreAr;
use srag\asq\Infrastructure\Persistence\Projection\QuestionAr;
use srag\asq\Infrastructure\Persistence\Projection\QuestionListItemAr;
use srag\asq\Infrastructure\Setup\lang\SetupAsqLanguages;
use srag\asq\Infrastructure\Setup\sql\SetupDatabase;
use srag\asq\Test\AsqTestGateway;
use srag\asq\Test\Domain\Result\Model\AssessmentResultContext;
use srag\asq\Test\Domain\Result\Persistence\AssessmentResultEventStoreAr;
use srag\asq\Test\Domain\Section\Model\AssessmentSectionDto;
use srag\asq\Test\Infrastructure\Setup\lang\SetupAsqTestLanguages;
use srag\asq\Test\Infrastructure\Setup\sql\SetupAsqTestDatabase;
use srag\asq\Test\Application\TestRunner\TestRunnerService;
use srag\asq\Test\Domain\Section\Persistence\AssessmentSectionEventStoreAr;
use srag\asq\Infrastructure\Persistence\QuestionType;

/**
 * Class ilObjAssessmentTestGUI
 *
 * Generated by SrPluginGenerator v1.3.5
 *
 * @author studer + raimann ag - Adrian Lüthi <al@studer-raimann.ch>
 *
 * @ilCtrl_isCalledBy ilObjAssessmentTestGUI: ilRepositoryGUI
 * @ilCtrl_isCalledBy ilObjAssessmentTestGUI: ilObjPluginDispatchGUI
 * @ilCtrl_isCalledBy ilObjAssessmentTestGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: ilPermissionGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: ilInfoScreenGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: ilObjectCopyGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: AsqQuestionAuthoringGUI
 * @ilCtrl_Calls      ilObjAssessmentTestGUI: TestPlayerGUI
 */
class ilObjAssessmentTestGUI extends ilObjectPluginGUI implements IAuthoringCaller
{

    use DICTrait;
    use AssessmentTestTrait;
    const PLUGIN_CLASS_NAME = ilAssessmentTestPlugin::class;
    const CMD_SHOW_QUESTIONS = "showQuestions";
    const CMD_PERMISSIONS = "perm";
    const CMD_SETTINGS = "settings";
    const CMD_SETTINGS_STORE = "settingsStore";
    const CMD_SHOW_CONTENTS = "showTest";
    const CMD_INIT_ASQ = "initASQ";
    const CMD_CLEAR_ASQ = "clearASQ";
    const LANG_MODULE_OBJECT = "object";
    const LANG_MODULE_SETTINGS = "settings";
    const TAB_CONTENTS = "contents";
    const TAB_PERMISSIONS = "perm_settings";
    const TAB_SETTINGS = "settings";
    const TAB_QUESTIONS = "questions";

    const COL_TITLE = 'QUESTION_TITLE';
    const COL_TYPE = 'QUESTION_TYPE';
    const COL_AUTHOR = 'QUESTION_AUTHOR';
    const COL_EDITLINK = "QUESTION_EDITLINK";
    const VAL_NO_TITLE = '-----';

    /**
     * @var ilObjAssessmentTest
     */
    public $object;

    /**
     * @var AssessmentSectionDto
     */
    private $section;

    /**
     * @inheritDoc
     */
    protected function afterConstructor()/*: void*/
    {
        //TODO this will be replaced with usable code
        if (!is_null($this->object)) {
            $section_id = $this->object->getData();

            if (is_null($section_id)) {
                $section_id = AsqTestGateway::get()->section()->createSection();
                $this->object->setData($section_id);
                $this->object->doUpdate();
            }

            try {
                $this->section = AsqTestGateway::get()->section()->getSection($section_id);
            }
            catch (CQRSException $e) {
                $section_id = AsqTestGateway::get()->section()->createSection();
                $this->object->setData($section_id);
                $this->object->doUpdate();

                $this->section = AsqTestGateway::get()->section()->getSection($section_id);
            }
        }
    }


    /**
     * @inheritDoc
     */
    public final function getType(): string
    {
        return ilAssessmentTestPlugin::PLUGIN_ID;
    }

    /**
     *
     * @param string $cmd
     */
    public function performCommand(string $cmd)/*: void*/
    {
        self::dic()->help()->setScreenIdComponent(ilAssessmentTestPlugin::PLUGIN_ID);

        $next_class = self::dic()->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            case strtolower(AsqQuestionAuthoringGUI::class):
                self::dic()->tabs()->activateTab(self::TAB_QUESTIONS);
                $this->showAuthoring();
                return;
            case strtolower(TestPlayerGUI::class):
                self::dic()->tabs()->activateTab(self::TAB_CONTENTS);
                self::dic()->ctrl()->forwardCommand(new TestPlayerGUI());
                return;
            default:
                switch ($cmd) {
                    case self::CMD_SHOW_CONTENTS:
                    case self::CMD_SHOW_QUESTIONS:
                    case self::CMD_SETTINGS:
                    case self::CMD_SETTINGS_STORE:
                    case self::CMD_INIT_ASQ:
                    case self::CMD_CLEAR_ASQ:
                        // Write commands
                        if (!ilObjAssessmentTestAccess::hasWriteAccess()) {
                            ilObjAssessmentTestAccess::redirectNonAccess($this);
                        }

                        $this->{$cmd}();
                        break;

                    default:
                        // Unknown command
                        ilObjAssessmentTestAccess::redirectNonAccess(ilRepositoryGUI::class);
                        break;
                }
                break;
        }
    }

    /**
     *
     */
    private function showAuthoring()
    {
        $backLink = self::dic()->ui()->factory()->link()->standard(
            self::dic()->language()->txt('back'),
            self::dic()->ctrl()->getLinkTarget($this, self::CMD_SHOW_QUESTIONS));


        $authoring_context_container = new AuthoringContextContainer(
            $backLink,
            $this->object->getRefId(),
            $this->object->getId(),
            $this->object->getType(),
            self::dic()->user()->getId(),
            $this);

        $asq = new AsqQuestionAuthoringGUI($authoring_context_container);

        self::dic()->ctrl()->forwardCommand($asq);
    }



    /**
     * @param string $html
     */
    protected function show(string $html)/*: void*/
    {
        if (!self::dic()->ctrl()->isAsynch()) {
            self::dic()->ui()->mainTemplate()->setTitle($this->object->getTitle());

            self::dic()->ui()->mainTemplate()->setDescription($this->object->getDescription());

            if (!$this->object->isOnline()) {
                self::dic()->ui()->mainTemplate()->setAlertProperties([
                    [
                        "alert"    => true,
                        "property" => self::plugin()->translate("status", self::LANG_MODULE_OBJECT),
                        "value"    => self::plugin()->translate("offline", self::LANG_MODULE_OBJECT)
                    ]
                ]);
            }
        }

        self::output()->output($html);
    }


    /**
     * @inheritDoc
     */
    public function initCreateForm(/*string*/ $a_new_type) : ilPropertyFormGUI
    {
        $form = parent::initCreateForm($a_new_type);

        return $form;
    }


    /**
     * @inheritDoc
     *
     * @param ilObjAssessmentTest $a_new_object
     */
    public function afterSave(/*ilObjAssessmentTest*/ ilObject $a_new_object)/*: void*/
    {
        parent::afterSave($a_new_object);
    }


    /**
     *
     */
    protected function showTest()/*: void*/
    {
        $srv = new TestRunnerService();

        $context_uid = $srv->createTestRun(
            AssessmentResultContext::create(self::dic()->user()->getId(), 'testrun'),
            array_map(function($question) {
                return $question->getId();
            }, $this->section->getItems()));

        self::dic()->ctrl()->setParameterByClass(TestPlayerGUI::class, TestPlayerGUI::PARAM_CURRENT_RESULT, $context_uid);
        self::dic()->ctrl()->redirectToURL(
            self::dic()->ctrl()->getLinkTargetByClass(TestPlayerGUI::class, TestPlayerGUI::CMD_RUN_TEST, "", false, false)
        );
    }


    /**
     *
     */
    protected function showQuestions()/*: void*/
    {
        self::dic()->tabs()->activateTab(self::TAB_QUESTIONS);

        $link = AsqGateway::get()->link()->getCreationLink();
        $button = ilLinkButton::getInstance();
        $button->setUrl($link->getAction());
        $button->setCaption($link->getLabel(), false);
        self::dic()->toolbar()->addButtonInstance($button);

        $button = ilLinkButton::getInstance();
        $button->setUrl(self::dic()->ctrl()->getLinkTargetByClass(self::class, self::CMD_INIT_ASQ));
        $button->setCaption("Init ASQ", false);
        self::dic()->toolbar()->addButtonInstance($button);

        $button = ilLinkButton::getInstance();
        $button->setUrl(self::dic()->ctrl()->getLinkTargetByClass(self::class, self::CMD_CLEAR_ASQ));
        $button->setCaption("Clear ASQ", false);
        self::dic()->toolbar()->addButtonInstance($button);

        $question_table = new ilTable2GUI($this);
        $question_table->setRowTemplate("tpl.questions_row.html", "Customizing/global/plugins/Services/Repository/RepositoryObject/AssessmentTest");
        $question_table->addColumn(self::plugin()->translate("header_title"), self::COL_TITLE);
        $question_table->addColumn(self::plugin()->translate("header_type"), self::COL_TYPE);
        $question_table->addColumn(self::plugin()->translate("header_creator"), self::COL_AUTHOR);

        $question_table->setData($this->getQuestionsOfContainerAsAssocArray());

        $this->show($question_table->getHTML());
    }

    private function getQuestionsOfContainerAsAssocArray() : array
    {
        $assoc_array = [];
        $items = $this->section->getItems();

        if (is_null($items)) {
            return $assoc_array;
        }

        foreach($items as $item) {
            $question_dto = AsqGateway::get()->question()->getQuestionByQuestionId($item->getId());

            $data = $question_dto->getData();

            $question_array[self::COL_TITLE] = is_null($data) ? self::VAL_NO_TITLE : $data->getTitle() ?? self::VAL_NO_TITLE;
            $question_array[self::COL_TYPE] = $question_dto->getType()->getTitle();
            $question_array[self::COL_AUTHOR] = is_null($data) ? '' : $data->getAuthor();
            $question_array[self::COL_EDITLINK] = AsqGateway::get()->link()->getEditLink($question_dto->getId(), array_map(function($item) {
                return $item['class'];
            }, self::dic()->ctrl()->getCallHistory()))->getAction();

            $assoc_array[] = $question_array;
        }

        return $assoc_array;
    }

    protected function initASQ() {
        QuestionType::resetDB();
        SetupDatabase::new()->run();
        SetupAsqLanguages::new()->run();
        SetupAsqTestDatabase::run();
        SetupAsqTestLanguages::new()->run();

        $this->showQuestions();
    }

    protected function clearASQ() {
        global $DIC;

        QuestionEventStoreAr::resetDB();
        QuestionListItemAr::resetDB();
        QuestionAr::resetDB();
        SimpleStoredAnswer::resetDB();
        AssessmentResultEventStoreAr::resetDB();
        AssessmentSectionEventStoreAr::resetDB();
        QuestionType::resetDB();

        //resetup asq for question types
        SetupDatabase::new()->run();

        $DIC->ctrl()->redirectToURL($DIC->ctrl()->getLinkTarget($this, self::CMD_SHOW_QUESTIONS, "", false, false));
    }

    /**
     * @param string $uuid
     */
    public function afterQuestionCreated(QuestionDto $question)
    {
        AsqTestGateway::get()->section()->addQuestion($this->section->getId(), $question->getId());
    }

    /**
     * @return ObjectSettingsFormGUI
     */
    protected function getSettingsForm() : ObjectSettingsFormGUI
    {
        $form = new ObjectSettingsFormGUI($this, $this->object);

        return $form;
    }


    /**
     *
     */
    protected function settings()/*: void*/
    {
        self::dic()->tabs()->activateTab(self::TAB_SETTINGS);

        $form = $this->getSettingsForm();

        self::output()->output($form);
    }


    /**
     *
     */
    protected function settingsStore()/*: void*/
    {
        self::dic()->tabs()->activateTab(self::TAB_SETTINGS);

        $form = $this->getSettingsForm();

        if (!$form->storeForm()) {
            self::output()->output($form);

            return;
        }

        ilUtil::sendSuccess(self::plugin()->translate("saved", self::LANG_MODULE_SETTINGS), true);

        self::dic()->ctrl()->redirect($this, self::CMD_SETTINGS);
    }


    /**
     *
     */
    protected function setTabs()/*: void*/
    {
        self::dic()->tabs()->addTab(self::TAB_CONTENTS, self::plugin()->translate("show_contents", self::LANG_MODULE_OBJECT), self::dic()->ctrl()
            ->getLinkTarget($this, self::CMD_SHOW_CONTENTS));

        if (ilObjAssessmentTestAccess::hasWriteAccess()) {
            self::dic()->tabs()->addTab(self::TAB_QUESTIONS, self::plugin()->translate("questions", self::LANG_MODULE_OBJECT), self::dic()
                ->ctrl()->getLinkTarget($this, self::CMD_SHOW_QUESTIONS));

            self::dic()->tabs()->addTab(self::TAB_SETTINGS, self::plugin()->translate("settings", self::LANG_MODULE_SETTINGS), self::dic()->ctrl()
                ->getLinkTarget($this, self::CMD_SETTINGS));
        }

        if (ilObjAssessmentTestAccess::hasEditPermissionAccess()) {
            self::dic()->tabs()->addTab(self::TAB_PERMISSIONS, self::plugin()->translate(self::TAB_PERMISSIONS, "", [], false), self::dic()->ctrl()
                ->getLinkTargetByClass([
                    self::class,
                    ilPermissionGUI::class
                ], self::CMD_PERMISSIONS));
        }

        self::dic()->tabs()->manual_activation = true; // Show all tabs as links when no activation
    }


    /**
     * @return string
     */
    public static function getStartCmd() : string
    {
        if (ilObjAssessmentTestAccess::hasWriteAccess()) {
            return self::CMD_SHOW_QUESTIONS;
        } else {
            return self::CMD_SHOW_CONTENTS;
        }
    }


    /**
     * @inheritDoc
     */
    public function getAfterCreationCmd() : string
    {
        return self::getStartCmd();
    }


    /**
     * @inheritDoc
     */
    public function getStandardCmd() : string
    {
        return self::getStartCmd();
    }
}
