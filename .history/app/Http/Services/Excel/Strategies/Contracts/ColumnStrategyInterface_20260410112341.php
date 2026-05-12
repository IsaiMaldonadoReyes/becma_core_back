<?php 

interface ColumnStrategyInterface
{
    public function apply($sheet, $columnConfig, $row);
}