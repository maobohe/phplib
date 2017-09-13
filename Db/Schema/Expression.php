<?php
namespace Lib\Db\Schema;

class Expression
{
    /**
     * @var string the DB expression
     */
    public $expression;
    /**
     * @var array list of parameters that should be bound for this expression.
     * The keys are placeholders appearing in {@link expression}, while the values
     * are the corresponding parameter values.
     * @since 1.1.1
     */
    public $params = array();

    /**
     * Constructor.
     * @param string $expression the DB expression
     * @param array $params parameters
     */
    public function __construct($expression, $params = array())
    {
        $this->expression = $expression;
        $this->params = $params;
    }

    /**
     * String magic method
     * @return string the DB expression
     */
    public function __toString()
    {
        return $this->expression;
    }
}