<?php

/**
 * Plugin RefNotes: Note
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Mykola Ostrovskyy <spambox03@mail.ru>
 */

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_block_iterator extends FilterIterator {
    private $note;
    private $limit;
    private $count;

    /**
    * Constructor
    */
    public function __construct($note, $limit) {
        $this->note = new ArrayObject($note);
        $this->limit = $this->getBlockLimit($limit);
        $this->count = 0;

        parent::__construct($this->note->getIterator());
    }

    /**
     *
     */
    function accept() {
        $result = $this->current()->isValid();

        if ($result) {
            ++$this->count;
        }

        return $result;
    }

    /**
     *
     */
    function valid() {
        return parent::valid() && (($this->limit == 0) || ($this->count <= $this->limit));
    }

    /**
     *
     */
    private function getBlockLimit($limit) {
        if (preg_match('/(\/?)(\d+)/', $limit, $match) == 1) {
            if ($match[1] != '') {
                $devider = intval($match[2]);
                $result = ceil($this->getValidCount() / $devider);
            }
            else {
                $result = intval($match[2]);
            }
        }
        else {
            $result = 0;
        }

        return $result;
    }

    /**
     *
     */
    private function getValidCount() {
        $result = 0;

        foreach ($this->note as $note) {
            if ($note->isValid()) {
                ++$result;
            }
        }

        return $result;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note_mock {

    /**
     *
     */
    public function setText($text) {
    }

    /**
     *
     */
    public function addReference($reference) {
        return new refnotes_reference_mock($this);
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_note extends refnotes_refnote {

    protected $scope;
    protected $id;
    protected $name;
    protected $inline;
    protected $reference;
    protected $text;
    protected $processed;

    /**
     * Constructor
     */
    public function __construct($scope, $name) {
        parent::__construct();

        $this->scope = $scope;
        $this->id = -1;
        $this->name = $name;
        $this->inline = false;
        $this->reference = array();
        $this->text = '';
        $this->processed = false;
    }

    /**
     *
     */
    private function initId() {
        $this->id = $this->scope->getNoteId();

        if ($this->name == '') {
            $this->name = '#' . $this->id;
        }
    }

    /**
     *
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     */
    public function getScope() {
        return $this->scope;
    }

    /**
     *
     */
    public function setText($text) {
        if (($this->text == '') || !$this->inline) {
            $this->text = $text;
        }
    }

    /**
     *
     */
    public function getText() {
        return $this->text;
    }

    /**
     *
     */
    public function addReference($reference) {
        if ($this->id == -1 && !$this->inline) {
            $this->inline = $reference->isInline();

            if (!$this->inline) {
                $this->initId();
            }
        }

        if ($reference->isBackReferenced()) {
            $this->reference[] = $reference;
            $this->processed = false;
        }
    }

    /**
     * Checks if the note should be processed
     */
    public function isValid() {
        return !$this->processed && (count($this->reference) > 0) && ($this->text != '');
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_renderer_note extends refnotes_note {

    /**
     *
     */
    public function getAnchorName() {
        $result = 'refnotes';
        $result .= $this->scope->getName();
        $result .= ':note' . $this->id;

        return $result;
    }

    /**
     *
     */
    public function render() {
        $html = $this->scope->getRenderer()->renderNote($this, $this->reference);

        $this->reference = array();
        $this->processed = true;

        return $html;
    }
}

////////////////////////////////////////////////////////////////////////////////////////////////////
class refnotes_action_note extends refnotes_note {

    /**
     * Constructor
     */
    public function __construct($scope, $name) {
        parent::__construct($scope, $name);

        $this->loadDatabaseDefinition();

        $this->inline = $this->getAttribute('inline', false);
    }

    /**
     *
     */
    private function loadDatabaseDefinition() {
        $name = $this->scope->getNamespaceName() . $this->name;
        $note = refnotes_reference_database::getInstance()->findNote($name);

        if ($note != NULL) {
            $this->attributes = $note->getAttributes();
            $this->data = $note->getData();
        }
    }

    /**
     *
     */
    public function addReference($reference) {
        parent::addReference($reference);

        $this->data = array_merge($this->data, $reference->getData());
    }

    /**
     * Checks if the note should be processed. Simple notes are also reported as valid so that
     * scope limits will produce note blocks identical to ones during rendering.
     */
    public function isValid() {
        return !$this->processed && (count($this->reference) > 0) && (($this->text != '') || $this->hasData());
    }

    /**
     * Inject reference database data into references so that they can be properly rendered.
     * Inject note text into the last reference.
     */
    public function rewriteReferences() {
        if (($this->text == '') && $this->hasData()) {
            foreach ($this->reference as $reference) {
                $reference->updateAttributes($this->attributes);
                $reference->updateData($this->data);
                $reference->rewrite();
            }

            $this->reference[0]->setNoteText($this->scope->getRenderer()->renderNoteText($this));
        }

        $this->processed = true;
    }
}
