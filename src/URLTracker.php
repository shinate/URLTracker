<?php

class URLTracker
{
    private static $RULE = [];

    private static $CURRENT = [];

    private static $__Getter;

    private static $__Builder;

    /**
     * Ser getter callback
     *
     * @param $factory
     */
    public static function setGetter($factory) {
        if (is_callable($factory)) {
            self::$__Getter = func_get_args();
        }
    }

    /**
     * Set builder callback
     *
     * @param $factory
     */
    public static function setBuilder($factory) {
        if (is_callable($factory)) {
            self::$__Builder = func_get_args();
        }
    }


    /**
     * the default getter
     *
     * @param string $key
     * @param null   $default
     *
     * @return null
     */
    private static function defaultGetter($key, $default = null) {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    /**
     * The default builder
     *
     * @param array $query
     *
     * @return array
     */
    private static function defaultBuilder($query = []) {
        return $query;
    }

    /**
     * Get param value
     *
     * @param string $key
     * @param null   $default
     *
     * @return mixed
     */
    public static function getParam($key, $default = null) {
        return call_user_func_array(self::$__Getter[0], [$key, $default] + array_slice(self::$__Getter, 1));
    }

    /**
     * Build query use default or inject callback
     *
     * @param array $params
     *
     * @return mixed
     */
    public static function buildQuery($params = []) {
        if (empty($params)) {
            $params = self::$CURRENT;
        }

        return call_user_func_array(self::$__Builder[0], [
                                                             self::diff(empty($params) ? self::$CURRENT
                                                                 : $params, self::$RULE),
                                                         ] + array_slice(self::$__Builder, 1));
    }

    /**
     * initialize
     *
     * @param array $rule
     *
     * @return array
     */
    public static function init(array $rule) {

        if (!isset(self::$__Getter)) {
            self::setGetter([__CLASS__, 'defaultGetter']);
        }

        if (!isset(self::$__Builder)) {
            self::setBuilder([__CLASS__, 'defaultBuilder']);
        }

        self::rule($rule);

        $_vs = [];

        foreach (self::$RULE as $ruleKey => $ruleValue) {
            $value = self::reflex(self::getParam($ruleKey, $ruleValue), $ruleValue);
            if (is_array($ruleValue)) {
                if (is_array($value)) {
                    $_vs[$ruleKey] = array_replace_recursive($ruleValue, $value);
                } else {
                    $_vs[$ruleKey] = $ruleValue;
                }
            } else {
                $_vs[$ruleKey] = $value;
            }
        }

        self::$CURRENT = $_vs;

        return self::$CURRENT;
    }

    /**
     * set/get rule
     *
     * @param array $rule
     *
     * @return mixed
     */
    public static function rule($rule = []) {

        if (empty($rule)) {
            return self::$RULE;
        }

        if (!self::isAssoc($rule)) {
            $rule = array_fill_keys($rule, null);
        }

        self::$RULE = $rule;
    }

    /**
     * return current list
     *
     * @return mixed
     */
    public static function current() {
        return self::$CURRENT;
    }

    /**
     * return current available list
     *
     * @return array
     */
    public static function all() {
        return self::diff(self::$CURRENT, self::$RULE);
    }

    /**
     * parse query args and return query string
     *
     * @param array $params
     * @param bool  $cover
     *
     * @return string
     */
    public static function parse($params = null, $cover = false) {

        $haystack = self::$CURRENT;

        if (!empty($params)) {

            // parse query_string to array
            if (is_string($params)) {
                parse_str($params, $_Q);
                $params = $_Q;
            }

            $haystack = array_replace_recursive($haystack, self::reflexWalker($params, self::$RULE));

            if ($cover) {
                self::$CURRENT = $haystack;
            }
        }

        return self::buildQuery($haystack);
    }

    /**
     * a single version of self::parse
     *
     * @param      $key
     * @param      $value
     * @param bool $cover
     *
     * @return string
     */
    public static function assign($key = null, $value = null, $cover = false) {

        $arr = null;

        if (!is_null($key) && !is_null($value)) {
            $args = explode('.', $key);
            $arr = [];
            $len = count($args);
            if ($len > 1) {
                $cur = &$arr;
                for ($i = 0; $i < $len - 1; $i++) {
                    $key = $args[$i];
                    $cur[$key] = [];
                    $cur = &$cur[$key];
                }
                $cur[end($args)] = $value;
            } else {
                $arr[$key] = $value;
            }
        }

        return self::parse($arr, $cover);
    }

    /**
     * drop query args and return query string
     *
     * @return string
     */
    public static function except() {
        $args = func_get_args();
        $C = self::$CURRENT;

        if (count($args) > 0) {
            foreach ($args as $param) {
                if (array_key_exists($param, $C) && array_key_exists($param, self::$RULE)) {
                    $C[$param] = self::$RULE[$param];
                }
            }
        }

        return self::buildQuery($C);
    }

    /**
     * get current value by key
     *
     * @param $key
     *
     * @return mixed
     */
    public static function val($key = null) {
        $args = is_string($key) ? explode('.', $key) : null;
        $value = &self::$CURRENT;

        if ($args) {
            foreach ($args as $k) {
                if (isset($value[$k])) {
                    $value = &$value[$k];
                } else {
                    return null;
                }
            }
        }

        return $value;
    }

    public static function form_val() {
        $args = func_get_args();
        $len = count($args);
        if ($len > 0) {
            if ($len == 1) {
                $name = $args[0];
            } else {
                $name = $args[0] . '[' . join('][', array_slice($args, 1)) . ']';
            }

            $value = call_user_func_array([self, 'val'], $args);

            if (!is_null($value)) {
                return '<input type="hidden" name="' . $name . '" value="' . $value . '"/>';
            }
        }

        return '';
    }

    /**
     * set current value to default, if it is exist
     */
    public static function del() {
        $args = func_get_args();
        $value = null;
        if (count($args)) {
            foreach ($args as $k => $v) {
                $args[$k] = is_int($v) ? $v : '\'' . $v . '\'';
            }
            $args = '[' . join('][', $args) . ']';
            @eval('$value = self::$CURRENT' . $args . ';');
            if (!is_null($value)) {
                @eval('self::$CURRENT' . $args . ' = self::$RULE' . $args . ';');
            }
        }
    }

    /**
     * deff
     *
     * @param       $arr
     * @param array $against
     *
     * @return array
     */
    public static function diff($arr, $against = []) {
        $na = [];
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                if (array_key_exists($k, $against)) {
                    // they are all array
                    if (is_array($v) && is_array($against[$k])) {
                        $cmb = self::diff($v, $against[$k]);
                        if (!empty($cmb)) {
                            $na[$k] = $cmb;
                        }
                    } elseif ($v !== $against[$k]) {
                        $na[$k] = $arr[$k];
                    }
                } else {
                    $na[$k] = $v;
                }
            }
        }

        return $na;
    }

    private static function reflexWalker(array $values, array $rule) {
        foreach ($values as $key => $value) {
            if (isset($rule[$key])) {
                $values[$key] = self::reflex($value, $rule[$key]);
            } else {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /**
     * convert types
     *
     * @param        $value
     * @param string $ruleValue
     *
     * @return array|float|int|string
     */
    private static function reflex($value, $ruleValue = null) {
        switch (true) {
            case is_string($ruleValue):
                return (string)$value;
            case is_int($ruleValue):
                return (int)$value;
            case is_array($ruleValue):
                return (array)$value;
            case is_double($ruleValue):
                return (double)$value;
            case is_float($ruleValue):
                return (float)$value;
            default:
                return $value;
        }
    }

    /**
     * is as assoc array
     *
     * @param array $arr
     *
     * @return bool
     */
    private static function isAssoc(array $arr) {
        return [] === $arr ? false : (array_keys($arr) !== range(0, count($arr) - 1));
    }
}