<?php

namespace TheCodingMachine\TDBM\Utils;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

/**
 * This class represent a property in a bean (a property has a getter, a setter, etc...).
 */
class ScalarBeanPropertyDescriptor extends AbstractBeanPropertyDescriptor
{
    /**
     * @var Column
     */
    private $column;

    /**
     * ScalarBeanPropertyDescriptor constructor.
     * @param Table $table
     * @param Column $column
     * @param NamingStrategyInterface $namingStrategy
     */
    public function __construct(Table $table, Column $column, NamingStrategyInterface $namingStrategy)
    {
        parent::__construct($table, $namingStrategy);
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Returns the foreign-key the column is part of, if any. null otherwise.
     *
     * @return ForeignKeyConstraint|null
     */
    public function getForeignKey()
    {
        return false;
    }

    /**
     * Returns the param annotation for this property (useful for constructor).
     *
     * @return string
     */
    public function getParamAnnotation()
    {
        $paramType = $this->getPhpType();

        $str = '     * @param %s %s';

        return sprintf($str, $paramType, $this->getVariableName());
    }

    /**
     * Returns the name of the class linked to this property or null if this is not a foreign key.
     *
     * @return null|string
     */
    public function getClassName(): ?string
    {
        return null;
    }

    /**
     * Returns the PHP type for the property (it can be a scalar like int, bool, or class names, like \DateTimeInterface, App\Bean\User....)
     *
     * @return string
     */
    public function getPhpType(): string
    {
        $type = $this->column->getType();
        return TDBMDaoGenerator::dbalTypeToPhpType($type);
    }

    /**
     * Returns true if the property is compulsory (and therefore should be fetched in the constructor).
     *
     * @return bool
     */
    public function isCompulsory()
    {
        return $this->column->getNotnull() && !$this->column->getAutoincrement() && $this->column->getDefault() === null;
    }

    /**
     * Returns true if the property has a default value.
     *
     * @return bool
     */
    public function hasDefault()
    {
        return $this->column->getDefault() !== null;
    }

    /**
     * Returns the code that assigns a value to its default value.
     *
     * @return string
     */
    public function assignToDefaultCode()
    {
        $str = '        $this->%s(%s);';

        $default = $this->column->getDefault();

        if (strtoupper($default) === 'CURRENT_TIMESTAMP') {
            $defaultCode = 'new \DateTimeImmutable()';
        } else {
            $defaultCode = var_export($this->column->getDefault(), true);
        }

        return sprintf($str, $this->getSetterName(), $defaultCode);
    }

    /**
     * Returns true if the property is the primary key.
     *
     * @return bool
     */
    public function isPrimaryKey()
    {
        return in_array($this->column->getName(), $this->table->getPrimaryKeyColumns());
    }

    /**
     * Returns the PHP code for getters and setters.
     *
     * @return string
     */
    public function getGetterSetterCode()
    {
        $normalizedType = $this->getPhpType();

        $columnGetterName = $this->getGetterName();
        $columnSetterName = $this->getSetterName();

        // A column type can be forced if it is not nullable and not auto-incrementable (for auto-increment columns, we can get "null" as long as the bean is not saved).
        $isNullable = !$this->column->getNotnull() || $this->column->getAutoincrement();

        $getterAndSetterCode = '    /**
     * The getter for the "%s" column.
     *
     * @return %s
     */
    public function %s() : %s%s
    {
        return $this->get(%s, %s);
    }

    /**
     * The setter for the "%s" column.
     *
     * @param %s $%s
     */
    public function %s(%s%s $%s) : void
    {
        $this->set(%s, $%s, %s);
    }

';

        return sprintf($getterAndSetterCode,
            // Getter
            $this->column->getName(),
            $normalizedType.($isNullable ? '|null' : ''),
            $columnGetterName,
            ($isNullable ? '?' : ''),
            $normalizedType,
            var_export($this->column->getName(), true),
            var_export($this->table->getName(), true),
            // Setter
            $this->column->getName(),
            $normalizedType,
            $this->column->getName(),
            $columnSetterName,
            $this->column->getNotnull() ? '' : '?',
            $normalizedType,
                //$castTo,
            $this->column->getName(),
            var_export($this->column->getName(), true),
            $this->column->getName(),
            var_export($this->table->getName(), true)
        );
    }

    /**
     * Returns the part of code useful when doing json serialization.
     *
     * @return string
     */
    public function getJsonSerializeCode()
    {
        $normalizedType = $this->getPhpType();

        if ($normalizedType == '\\DateTimeInterface') {
            return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = ($this->'.$this->getGetterName().'() === null) ? null : $this->'.$this->getGetterName()."()->format('c');\n";
        } else {
            return '        $array['.var_export($this->namingStrategy->getJsonProperty($this), true).'] = $this->'.$this->getGetterName()."();\n";
        }
    }

    /**
     * Returns the column name.
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->column->getName();
    }

}
