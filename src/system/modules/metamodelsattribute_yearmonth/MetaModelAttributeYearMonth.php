<?php

/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * @author     Oliver Hoff <oliver@hofff.com>
 * @copyright  The MetaModels team
 * @license    LGPL
 */

class MetaModelAttributeYearMonth extends MetaModelAttributeHybrid {

	public function setDataFor($values, $item = null) {
		if(!$item || !$values) {
			return;
		}
		$value = $this->valueOf($item);
		if(!$value) {
			return;
		}
		if($this->get('isunique') && !$this->isUnique($value, $item->get('id'))) {
			return;
		}

		$db = Database::getInstance();
		$query = sprintf('UPDATE %s SET %s = ?, %s = ? WHERE id IN (%s)',
			$this->getMetaModel()->getTableName(),
			$this->getYearColumnName(),
			$this->getMonthColumnName(),
			rtrim(str_repeat('?,', count($values)), ',')
		);

		$params[] = $value['year'];
		$params[] = $value['month'];
		$params = array_merge($params, array_keys($values));
		$db->prepare($query)->execute($params);
	}

	public function isUnique($value, $id = null) {
		$query = sprintf('SELECT id FROM %s WHERE %s = ? AND %s = ? AND id != ? LIMIT 1',
			$this->getMetaModel()->getTableName(),
			$this->getYearColumnName(),
			$this->getMonthColumnName()
		);
		return !Database::getInstance()->prepare($query)->execute($value['year'], $value['month'], intval($id))->numRows;
	}

	public function valueOf(MetaModelItem $item) {
		$value = (array) $item->get($this->getColName());
		$year = $item->get($this->getYearColumnName());
		$year === null || $value['year'] = $year;
		$month = $item->get($this->getMonthColumnName());
		$month === null || $value['month'] = $month;

		if(isset($value['year']) && isset($value['month'])) {
			$value['year'] = intval($value['year']);
			$value['month'] = max(1, min(12, intval($value['month'])));
			return $value;
		}
	}

	public function checkSaveable(MetaModelItem $item) {
		if(!$this->get('isunique')) {
			return;
		}

		$value = $this->valueOf($item);
		if($value && !$this->isUnique($value, $item->get('id'))) {
			throw new MetaModelItemNotSaveableException(sprintf(
				$GLOBALS['TL_LANG']['ERR']['unique'],
				$this->getLangValue($this->get('name'))
			), 1);
		}
	}

	public function getDataFor($ids, array $result = null) {
		$data = array();
		if(!$result) {
			return $data;
		}
		$columnName = $this->getColName();
		foreach($ids as $id) {
			$data[$id] = array(
				'year' => $result[$id][$columnName . '__year'],
				'month' => $result[$id][$columnName . '__month'],
			);
		}
		return $data;
	}

	public function unsetDataFor($ids) {
		// nothing
	}

	protected function prepareTemplate(MetaModelTemplate $objTemplate, $arrRowData, $objSettings = null)
	{
		parent::prepareTemplate($objTemplate, $arrRowData, $objSettings);
		$value = $arrRowData[$this->getColName()];
		$objTemplate->year = $value['year'];
		$objTemplate->month = $GLOBALS['TL_LANG']['MONTHS'][$value['month'] - 1];
	}

	public function getAttributeSettingNames() {
		return array_merge(parent::getAttributeSettingNames(), array(
			'filterable',
// 			'searchable',
			'sortable',
			'flag',
			'mandatory',
			'isunique',
			'includeBlankOption'
		));
	}

	public function sortIds($ids, $direction) {
		if(count($ids) < 2) {
			return $ids;
		}
		$direction == 'DESC' || $direction = 'ASC';
		$query = sprintf('SELECT id FROM %s WHERE id IN (%s) ORDER BY %s %s, %s %s',
			$this->getMetaModel()->getTableName(),
			rtrim(str_repeat('?,', count($ids)), ','),
			$this->getYearColumnName(),
			$direction,
			$this->getMonthColumnName(),
			$direction
		);
		return Database::getInstance()->prepare($query)->execute($ids)->fetchEach('id');
	}

	public $itemDCACalled;

	public function getColName() {
		// god kill me for this hack
		if(isset($this->itemDCACalled)) {
			unset($this->itemDCACalled);
			return $this->getYearColumnName() . ',' . $this->getMonthColumnName();
		}
		return parent::getColName();
	}

	public function getItemDCA($arrOverrides = array()) {
		$tableName = $this->getMetaModel()->getTableName();
		$columnName = $this->getColName();
		$yearColumnName = $this->getYearColumnName();
		$monthColumnName = $this->getMonthColumnName();

		$arrReturn['fields'][$columnName] = $this->getVirtualFieldDefinition($arrOverrides);
		$arrReturn['fields'][$yearColumnName] = $this->getYearFieldDefinition($arrOverrides);
		$arrReturn['fields'][$monthColumnName] = $this->getMonthFieldDefinition($arrOverrides);

		if($arrOverrides['sortable']) {
			$arrReturn['list']['sorting']['fields'][] = $columnName;
		}

		$this->itemDCACalled = true;
		return $arrReturn;
	}

	public function getYearColumnName() {
		return $this->getColName() . '__year';
	}

	public function getMonthColumnName() {
		return $this->getColName() . '__month';
	}

	public function getVirtualFieldDefinition(array $overrides = array()) {
		$tableName = $this->getMetaModel()->getTableName();
		$columnName = $this->getColName();
		$virtual['label'][0] = $this->getLangValue($this->get('name'));
		$virtual['label'][1] = $this->getLangValue($this->get('description'));
		if($overrides['sortable']) {
			$virtual['sorting'] = true;
			$overrides['flag'] && $virtual['flag'] = $overrides['flag'];
		}
		return array_merge($virtual, (array) $GLOBALS['TL_DCA'][$tableName]['fields'][$columnName]);
	}

	public function getYearFieldDefinition(array $arrOverrides = array()) {
		$tableName = $this->getMetaModel()->getTableName();
		$columnName = $this->getColName();
		$arrYear['label'][0] = $this->getLangValue($this->get('name')) . ' ' . $GLOBALS['TL_LANG']['MSC']['year'];
		$arrYear['label'][1] = $this->getLangValue($this->get('description'));
		$arrYear['inputType'] = 'text';
		$arrYear['default'] = date('Y');
		$arrYear['eval']['maxlength'] = 4;
		$arrOverrides['mandatory'] && $arrYear['eval']['mandatory'] = true;
		$arrYear['eval']['rgxp'] = 'digit';
		$arrYear['eval']['tl_class'] = 'clr w50';
		$arrOverrides['filterable'] && $arrYear['filter'] = true;
		return array_merge($arrYear, (array) $GLOBALS['TL_DCA'][$tableName]['fields'][$columnName . '__year']);
	}

	public function getMonthFieldDefinition(array $arrOverrides = array()) {
		$tableName = $this->getMetaModel()->getTableName();
		$columnName = $this->getColName();
		$arrMonth['label'][0] = $this->getLangValue($this->get('name')) . ' ' . $GLOBALS['TL_LANG']['MSC']['month'];
		$arrMonth['label'][1] = $this->getLangValue($this->get('description'));
		$arrMonth['inputType'] = 'select';
		$arrMonth['options'] = range(1, 12);
		$arrMonth['default'] = date('n');
		$arrMonth['reference'] = array_combine(range(1, 12), array_values($GLOBALS['TL_LANG']['MONTHS']));
		$arrOverrides['mandatory'] && $arrMonth['eval']['mandatory'] = true;
		$arrOverrides['includeBlankOption'] && $arrMonth['eval']['includeBlankOption'] = true;
		$arrMonth['eval']['tl_class'] = 'w50';
		$arrOverrides['filterable'] && $arrMonth['filter'] = true;
		return array_merge($arrMonth, (array) $GLOBALS['TL_DCA'][$tableName]['fields'][$columnName . '__month']);
	}

	public function createColumn() {
		$columnName = $this->getColName();
		if(!$columnName) {
			return;
		}
		$tableName = $this->getMetaModel()->getTableName();

		MetaModelTableManipulation::checkColumnName($columnName);
		MetaModelTableManipulation::createColumn($tableName, $columnName . '__year', 'int(4) NULL default NULL', true);
		MetaModelTableManipulation::createColumn($tableName, $columnName . '__month', 'tinyint(2) NULL default NULL', true);
	}

	public function deleteColumn() {
		$columnName = $this->getColName();
		if(!$columnName) {
			return;
		}
		$tableName = $this->getMetaModel()->getTableName();
		$db = Database::getInstance();

		MetaModelTableManipulation::checkColumnName($columnName);
		MetaModelTableManipulation::checkTableName($tableName);
		$dropYear = $db->fieldExists($columnName . '__year', $tableName);
		$dropYear && MetaModelTableManipulation::dropColumn($tableName, $columnName . '__year', true);
		$dropMonth = $db->fieldExists($columnName . '__month', $tableName);
		$dropMonth && MetaModelTableManipulation::dropColumn($tableName, $columnName . '__month', true);
	}

	public function renameColumn($newColumnName) {
		$oldColumnName = $this->getColName();
		$tableName = $this->getMetaModel()->getTableName();
		$db = Database::getInstance();

		try {
			MetaModelTableManipulation::checkColumnName($oldColumnName);
		} catch(Exception $e) {
			unset($oldColumnName);
		}
		MetaModelTableManipulation::checkColumnName($newColumnName);
		MetaModelTableManipulation::checkTableName($tableName);

		$renameYear = $oldColumnName && $db->fieldExists($oldColumnName . '__year', $tableName, true);
		$renameMonth = $oldColumnName && $db->fieldExists($oldColumnName . '__month', $tableName, true);

		if($renameYear) {
			MetaModelTableManipulation::renameColumn(
				$tableName,
				$oldColumnName . '__year',
				$newColumnName . '__year',
				'int(4) NULL default NULL',
				true
			);
		} else {
			MetaModelTableManipulation::createColumn(
				$tableName,
				$newColumnName . '__year',
				'int(4) NULL default NULL',
				true
			);
		}

		if($renameMonth) {
			MetaModelTableManipulation::renameColumn(
				$tableName,
				$oldColumnName . '__month',
				$newColumnName . '__month',
				'tinyint(2) NULL default NULL',
				true
			);
		} else {
			MetaModelTableManipulation::createColumn(
				$tableName,
				$newColumnName . '__month',
				'tinyint(2) NULL default NULL',
				true
			);
		}
	}

	public function getFieldDefinition($arrOverrides = array()) {
		throw new Exception('yearmonth does not support single widget definition');
	}

	public function getSQLDataType() {
		throw new Exception('yearmonth does not support single column storage');
	}

	public function hasOrder() {
		return true;
	}

	public function getOrderByExpr($desc = false) {
		$orderByExpr = $this->getYearColumnName();
		$desc && $orderByExpr .= ' DESC';
		$orderByExpr .= ', ' . $this->getMonthColumnName();
		$desc && $orderByExpr .= ' DESC';
		return $orderByExpr;
	}

}
