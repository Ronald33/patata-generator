<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'patata' . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'DB.php');

abstract class Helper
{
	private static $excepciones = 
	[
		'terminales' => 'terminal'
	];

    public static function getDBInstance() { return modules\patata\db\DB::getInstance(); }

    public static function getData()
	{
		$db = self::getDBInstance();
        $db->query('SHOW TABLES');
		$rows = $db->fetchAll();

		$data = [];
		foreach($rows as $row)
		{
			$table = reset(get_object_vars($row));
			$plural = self::toCamelCase($table);
			$object = self::toSingular($plural);
			$class = ucfirst($object);
			array_push($data, ['g_table' => $table, 'g_plural' => $plural, 'g_object' => $object, 'g_class' => $class]);
		}

		return $data;
	}

    public static function toCamelCase($input): string
	{
		// Divide las palabras por los guiones bajos
		$parts = explode('_', trim($input, '_'));
	
		// Convierte la primera parte a minúsculas y las demás a CapitalCase
		$camelCase = strtolower(array_shift($parts));

		foreach ($parts as $part) { $camelCase .= ucfirst(strtolower($part)); }
	
		return $camelCase;
	}

    public static function toSingular($plural): string
	{
		if(isset(self::$excepciones[$plural])) { return self::$excepciones[$plural]; }
		if (substr($plural, -3) === 'ces') { return substr($plural, 0, -3) . 'z'; }
		if (substr($plural, -1) === 's') { return substr($plural, 0, -1); }
		return $plural;
	}

    public static function getFields($table)
	{
		$db = self::getDBInstance();
		$db->query('SHOW COLUMNS FROM ' . $table);
		$results = $db->fetchAll();

		$simple = [];
		$tmp = [];
		foreach($results as $result)
		{
			if($result->Key != 'MUL')
			{
				$field = $result->Field;
				$field_without_prefix = substr($field, 5);
				$name = self::toCamelCase($field_without_prefix);
				preg_match('/^(\w+)(\((.*?)\))?$/', $result->Type, $matches);
				$type = $matches[1];
        		$param = isset($matches[3]) ? $matches[3] : null; 
				array_push($simple, ['field' => $field, 'field_without_prefix' => $field_without_prefix, 'name' => $name, 'Name' => ucfirst($name), 'allow_null' => $result->Null != 'NO', 'is_int' => $type == 'int', 'is_string' => $type == 'char' || $type == 'varchar', 'is_enum' => $type == 'enum', 'is_datetime' => $type == 'datetime', 'is_boolean' => $type == 'tinyint', 'param' => $param, 'is_unique' => $result->Key == 'UNI']);
			}
			else { $tmp[$result->Field] = $result->Null != 'NO'; }
		}

		$db->query('SHOW CREATE TABLE ' . $table);
		$result = $db->fetch()->{'Create Table'};
		preg_match_all('/FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)`/', $result, $matches, PREG_SET_ORDER);

		$complex = [];
		foreach($matches as $match)
		{
			$field = $match[1];
			$table = $match[2];
			$prefix = substr($table, 0, 4);
			$plural = self::toCamelCase($table);
			$object = self::toSingular($plural);
			$class = ucfirst($object);

			$field_without_prefix = substr($field, 5);
			$field_cleared = self::clearField($field_without_prefix);
			$name = strlen($field_cleared) == 0 ? $object : self::toCamelCase($field_cleared) . $class;

			array_push($complex, ['field' => $match[1], 'field_without_prefix' => $field_without_prefix, 'table' => $table, 'prefix' => $prefix, 'class' => $class, 'object' => $object, 'name' => $name, 'Name' => ucfirst($name), 'plural' => $plural, 'allow_null' => $tmp[$field]]);
		}

		return self::setExtraData($simple, $complex);
	}

	private static function setExtraData($simple, $complex)
	{
		$prefix = '';
		$combined = array_merge($simple, $complex);
		if(!empty($combined)) { $combined[0]['is_first'] = true; $combined[count($combined) - 1]['is_last'] = true; $prefix = substr($combined[0]['field'], 0, 4); }
	
		$simpleCount = count($simple);
		$simple_marked = array_slice($combined, 0, $simpleCount);
		$complex_marked = array_slice($combined, $simpleCount);

		$id = NULL;
		$simple_without_id = array_values(array_filter($simple, function($value) use (&$id) 
		{
			if($value['name'] == 'id') { $id = $value; return false; }
			return true; 
		}));

		$combined = array_merge($simple_without_id, $complex);
		if(!empty($combined)) { $combined[0]['is_first'] = true; $combined[count($combined) - 1]['is_last'] = true; }

		$simpleCount = count($simple_without_id);
		$simple_without_id = array_slice($combined, 0, $simpleCount);

		return ['simple' => $simple_marked, 'has_id' => isset($id), 'id' => $id, 'simple_without_id' => $simple_without_id, 'complex' => $complex_marked, 'g_prefix' => $prefix];
	}

	private static function clearField($field) { return preg_replace('/_?[a-z]{4}_id$/', '', $field); }
}