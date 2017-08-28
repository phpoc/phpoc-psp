<?php

// $psp_id sn_json.php date 20170410
// json finder

define("JSON_WS", "\x20\x09\x0a\x0d");

define("JSON_TYPE_UNDEF",  0);
define("JSON_TYPE_ARRAY",  1);
define("JSON_TYPE_OBJECT", 2);
define("JSON_TYPE_STRING", 3);
define("JSON_TYPE_INT",    4);
define("JSON_TYPE_FLOAT",  5);
define("JSON_TYPE_TRUE",   6);
define("JSON_TYPE_FALSE",  7);
define("JSON_TYPE_NULL",   8);

function sn_json_explode_key($key_list)
{
	$list_len = strlen($key_list);
	$prev_char = $key_list[0];

	$new_key_list = $prev_char;

	// insert "." in front of square bracket
	for($i = 1; $i < $list_len; $i++)
	{
		$next_char = $key_list[$i];

		if(($next_char == "[") && ($prev_char != "."))
			$new_key_list .= ".";

		if(($prev_char == "]") && ($next_char != ".") && ($next_char != "["))
			$new_key_list .= ".";

		$new_key_list .= $next_char;
		$prev_char = $next_char;
	}

	$key_array = explode(".", $new_key_list);
	$array_count = count($key_array);

	for($i = 0; $i < $array_count; $i++)
	{
		$key = $key_array[$i];

		if($key[0] == '[')
		{
			if($key[strlen($key) - 1] != "]")
				return false;

			$key = substr($key, 1); // remove left square bracket
			$key = substr($key, 0, -1); // remove right square bracket

			if(!$key || ltrim($key, "0123456789"))
				return false;

			$key_array[$i] = (int)$key;
		}
	}

	return $key_array;
}

function sn_json_cmp_skip(&$json, $token)
{
	$json_len = strlen($json);

	$token_len = strlen($token);

	if($token_len == 1)
	{
		if($json[0] != $token)
		{
			$json = ltrim($json, JSON_WS);

			if($json[0] != $token)
				return false;
		}
	}
	else
	{
		$json = ltrim($json, JSON_WS);

		if(substr($json, 0, $token_len) != $token)
			return false;
	}

	$json = substr($json, $token_len);

	return $json_len - strlen($json);
}

function sn_json_skip_name(&$json)
{
	$json_len = strlen($json);

	if($json[0] != "\"")
	{
		$json = ltrim($json, JSON_WS);

		if($json[0] != "\"")
			return false;
	}

	$name_len = strpos($json, "\"", 1);

	if($name_len === false)
		return false;

	$name_len = 1 + $name_len;

	$json = substr($json, $name_len);

	if($json[0] != ":")
	{
		$json = ltrim($json, JSON_WS);

		if($json[0] != ":")
			return false;
	}

	$json = substr($json, 1);

	return $json_len - strlen($json);
}

function sn_json_value_len($json)
{
	if(($json[0] == "[") || ($json[0] == "{"))
		return false;

	if($json[0] == "\"")
	{
		$name_len = strpos($json, "\"", 1);

		if($name_len === false)
			return false;

		return $name_len + 1;
	}
	else
	if(($json[0] == "-") || ($json[0] == "0") || ((int)$json > 0))
	{
		return strlen($json) - strlen(ltrim($json, "-+.eE0123456789"));
	}
	else
	{
		if(substr($json, 0, 4) == "true")
			return 4;
		if(substr($json, 0, 5) == "false")
			return 5;
		if(substr($json, 0, 4) == "null")
			return 4;

		return false;
	}
}

function sn_json_skip_value(&$json)
{
	$json_len = strlen($json);

	$json = ltrim($json, JSON_WS);

	if(($json[0] != "[") && ($json[0] != "{"))
	{
		$vlen = sn_json_value_len($json);

		if($vlen === false)
			return false;

		$json = substr($json, $vlen);

		return $json_len - strlen($json);
	}

	// 'else' statement removed to save task stack
	// two task stacks saved per depth throgh recursive function call

	if($json[0] == "{")
		$is_object = true;
	else
		$is_object = false;

	$json = substr($json, 1);

	while(1)
	{
		if($is_object && (sn_json_skip_name($json) === false))
			return false;

		// if(sn_json_skip_value($json) === false)
		// move sn_json_skip_value() out of if statment to save task stack
		// one task stack saved per depth through recursive function call
		$retval = sn_json_skip_value($json);
		if($retval === false)
			return false;

		if($json[0] != ",")
		{
			$json = ltrim($json, JSON_WS);

			if($json[0] != ",")
				break;
		}

		$json = substr($json, 1);
	}

	if($is_object)
	{
		if(sn_json_cmp_skip($json, "}") === false)
			return false;
	}
	else
	{
		if(sn_json_cmp_skip($json, "]") === false)
			return false;
	}

	return $json_len - strlen($json);
}

function json_search($json, $key_list)
{
	if(!$key_list)
		return "";

	$key_array = sn_json_explode_key($key_list);

	if($key_array === false)
	{
		echo "json_search: invalid key_list\r\n";
		return "";
	}

	$key_count = count($key_array);

	for($depth = 0; $depth < $key_count; $depth++)
	{
		$key = $key_array[$depth];

		if(is_int($key))
		{ // array
			if(sn_json_cmp_skip($json, "[") === false)
				return "";

			for($i = 0; $i < $key; $i++)
			{
				if(sn_json_skip_value($json) === false)
					return "";
				if(sn_json_cmp_skip($json, ",") === false)
					return "";
			}
		}
		else
		{ // object
			if(sn_json_cmp_skip($json, "{") === false)
				return "";

			while(1)
			{
				if(sn_json_cmp_skip($json, "\"" . $key . "\"") === false)
				{
					if(sn_json_skip_name($json) === false)
						return "";
					if(sn_json_skip_value($json) === false)
						return "";
					if(!sn_json_cmp_skip($json, ","))
						return "";
				}
				else
				{
					if(!sn_json_cmp_skip($json, ":"))
						return "";
					break;
				}
			}
		}
	}

	$json = ltrim($json, JSON_WS);

	$vlen = sn_json_value_len($json);

	if($vlen === false)
		return "";

	return substr($json, 0, $vlen);
}

function sn_json_unescape($text)
{
	// unescape single reverse solidus
	$next_offset = 0;
	while(1)
	{
		$esc_offset = strpos($text, "\x5c\x5c", $next_offset); // find '\\'
		if($esc_offset === false)
			break;

		$text = substr_replace($text, "\x5c", $esc_offset, 2);

		$next_offset = $esc_offset + 1;
	}

	// unescape \uXXXX
	$next_offset = 0;
	while(1)
	{
		$esc_offset = strpos($text, "\x5c\x75", $next_offset); // find '\u'

		if($esc_offset === false)
			break;

		if(strlen($text) < ($esc_offset + 6))
			break;

		$esc_hex = substr($text, $esc_offset + 2, 4);

		if(ltrim($esc_hex, "0123456789abcdefABCDEF"))
			$next_offset += 2;
		else
		{
			if(substr($esc_hex, 0, 2) == "00")
				$esc_bin = hex2bin(substr($esc_hex, 2));
			else
				$esc_bin = hex2bin($esc_hex);

			$text = substr_replace($text, $esc_bin, $esc_offset, 6);

			$next_offset = $esc_offset + strlen($esc_bin);
		}
	}

	return $text;
}

function json_text_value($text)
{
	if(!$text)
		return "";

	if(($text[0] == "[") || ($text[0] == "{"))
		return "";
	else
	if(($text == "true") || ($text == "false") || ($text == "null"))
		return 0;
	else
	if($text[0] == "\"")
	{
		$text = substr($text, 1); // remove left quotation mark
		$text = substr($text, 0, -1); // remove right quotation mark
		return sn_json_unescape($text);
	}
	else
	if(!ltrim($text, "-+.eE0123456789"))
	{
		if(!ltrim($text, "-0123456789"))
			return (int)$text;
		else
			return (float)$text;
	}
	else
		return false;
}

function json_text_type($text)
{
	if(!$text)
		return "";

	if($text[0] == "[")
		return JSON_TYPE_ARRAY;
	else
	if($text[0] == "{")
		return JSON_TYPE_OBJECT;
	else
	if($text[0] == "\"")
		return JSON_TYPE_STRING;
	else
	if($text == "true")
		return JSON_TYPE_TRUE;
	else
	if($text == "false")
		return JSON_TYPE_FALSE;
	else
	if($text == "null")
		return JSON_TYPE_NULL;
	else
	if(!ltrim($text, "-+.eE0123456789"))
	{
		if(!ltrim($text, "-0123456789"))
			return JSON_TYPE_INT;
		else
			return JSON_TYPE_FLOAT;
	}
	else
		return JSON_TYPE_UNDEF;
}

?>
