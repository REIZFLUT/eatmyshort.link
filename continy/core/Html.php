<?php

namespace Continy\Core;

class Html
{

    public static function input(string $type, string $name, string $value, array $args = [],  string $lang_context = '')
    {


        $real_value = (OldVal::exists($name)) ? OldVal::get($name) : $value;

        if (!empty($lang_context)) {
            $placeholder = Language::get($lang_context . '/placeholder/' . $name);
            if (!empty($placeholder)) {
                $args['placeholder'] = $placeholder;
            }
        }

        $output = '<input type="' . $type . '" name="' . $name . '" value="' . $real_value . '" ';

        foreach ($args as $key => $value) {
            $output .= $key . '="' . $value . '" ';
        }

        $output = trim($output) . '>' . "\n";

        return $output;
    }

    public static function csrf()
    {
        return "\t" . '<input type="hidden" name="__CSRF_Token" value="' . $_SESSION['__CSRF_Token'] . '">';
    }


    public static function formPostStart($rel, $pseudo_method = 'POST', $options_assoc = [])
    {
        $output = '<form method="POST" action="' . Config::get('app/url') . $rel . '" enctype="application/x-www-form-urlencoded" ';
        foreach ($options_assoc as $key => $value) {
            $output .= $key . ' = "' . $value . '" ';
        }
        $output .= '>' . "\n";
        $output .= "\t" . '<input type="hidden" name="__HTTP_Method" value="' . $pseudo_method . '">' . "\n";
        $output .= self::csrf() . "\n";

        return $output;
    }

    public static function formEnd()
    {
        return '</form>';
    }

    public static function submitBtn(string $name, array $args = [],  string $lang_context = '')
    {
        if (!empty($lang_context) && Language::exists($lang_context . '/button/' . $name)) {
            $value = Language::get($lang_context . '/button/' . $name);
        } else {
            $value = $name;
        }


        $output = '<button type="submit" ';

        foreach ($args as $key => $val) {
            $output .= $key . '="' . $val . '" ';
        }

        $output = trim($output) . '>';
        $output .= trim($value);
        $output .= '</button>' . "\n";

        return $output;
    }

    public static function autoTableRows(array $objects, array $fields, array $trAttrAssoc = [], array $tdAttrAssoc = [])
    {
        $trAttr = '';
        if (count($trAttrAssoc) > 0) {
            foreach ($trAttrAssoc as $key => $value) {
                $trAttr .= ' ' . $key . '="' . $value . '"';
            }
        }

        $tdAttr = '';
        if (count($tdAttrAssoc) > 0) {
            foreach ($tdAttrAssoc as $key => $value) {
                $tdAttr .= ' ' . $key . '="' . $value . '"';
            }
        }

        $output = '';
        foreach ($objects as $tr) {
            $output .= "<tr" . $trAttr . "> \n";

            foreach ($fields as $key => $options) {
                $real_key = preg_replace('/\*.*/', '', $key);
                $output .= self::prepareAutoTableColumn($tr->$real_key, $options, $key);
            }

            $output .= "</tr>\n";
        }

        return $output;
    }

    public static function prepareAutoTableColumn($str, $options, $td_class = '')
    {

        $open_tags = '';
        $clos_tags = '';
        $string    = (is_string($str)) ? $str : '';


        foreach ($options as $key => $value) {
            switch ($key) {
                case 'pre':
                    $string = $value . $string;
                    break;
                case 'post':
                    $string = $string . $value;
                    break;
                case 'link':
                case 'link_internal':
                    $open_tags  = $open_tags . '<a href="';
                    $open_tags .= ($value == '{self}') ? $string : $value;
                    $open_tags .= '">';
                    $clos_tags = '</a>' . $clos_tags;
                    break;
                case 'link_external':
                    $open_tags  = $open_tags . '<a href="';
                    $open_tags .= ($value == '{self}') ? $string : $value;
                    $open_tags .= '" target="_blank">';
                    $clos_tags = '</a>' . $clos_tags;
                    break;
                case 'link_route':
                    $open_tags  = $open_tags . '<a href="' . Config::get('app/url');
                    $open_tags .= preg_replace('/\{(.*)\}/', $str, $value);
                    $open_tags .= '">';
                    $clos_tags = '</a>' . $clos_tags;
                    break;
                case 'replace':
                    if ($value == '{raw}') {
                        $string = $str;
                    } else {
                        $string = $value;
                    }
                    break;
                case 'callback':
                    $string = call_user_func($value, $str);
                    break;
                case 'if':
                    if ($str) {
                        $string = $value;
                    }
                    break;
                case 'else':
                    if (!$str) {
                        $string = $value;
                    }
                    break;
                default:
                    break;
            }
        }

        if (!empty($td_class)) {
            $expl = explode('*', $td_class);
            $real_class = (count($expl) > 1) ? $expl[1] : $expl[0];
        }

        $class = (!empty($td_class)) ? ' class="' . $real_class . '">' . "\n" : '>' . "\n";

        return '<td' . $class . $open_tags . $string . $clos_tags . "\n</td>\n";
    }

    public static function routeLink(string $route, string $content, array $attrAssoc = [])
    {
        $url = Config::get('app/url') . $route;
        $attr = '';

        if ($route == Route::current()) {
            if (isset($attrAssoc['class'])) {
                $attrAssoc['class'] .= ' active';
            } else {
                $attrAssoc['class'] = 'active';
            }
        }


        foreach ($attrAssoc as $key => $value) {
            $attr .= $key . '="' . $value . '" ';
        }



        return '<a href="' . $url . '" ' . $attr . '>' . $content . '</a>';
    }

    public static function languageSwitch(string $route)
    {
        $baseUrl = Config::get('app/url');
        $available = Config::get('language/supported');

        $output = "\t" . '<ul class="language_switch">' . "\n";
        foreach ($available as $lang) {
            $url = $baseUrl . $route;
            $url = preg_replace('/\{(.*)\}/', strtolower($lang), $url);
            $return = (isset($_SERVER['PATH_INFO'])) ? '?return=' . urlencode($_SERVER['PATH_INFO']) : '';
            if ($lang == $_SESSION['LANGUAGE']) {
                $output .= "\t\t" . '<li class="active"><span class="active">' . strtoupper($lang) . '</span></li>' . "\n";
            } else {
                $output .= "\t\t" . '<li><a href="' . $url . $return . '">' . strtoupper($lang) . '</a></li>' . "\n";
            }
        }
        $output .= "\t</ul>\n";
        return $output;
    }

    public static function simplePagination(int $max, string $query)
    {

        $output = '<ul class="simplePagination">';
        $urls = [];
        for ($i = 1; $i <= $max; $i++) {
            if (count($_GET) > 0) {

                $new = '?';

                foreach ($_GET as $key => $val) {

                    if ($key !== $query) {
                        $new .= $key . '=' . urlencode($val) . '&';
                    } else {
                        $new .= $key . '=' . $i . '&';
                    }
                }

                $url = Config::get('app/url') . Route::current() . $new;
                $urls[] = (!isset($_GET[$query])) ? $url . $query . '=' . $i : $url;
            } else {
                $urls[] = Config::get('app/url') . Route::current() . '?' . $query . '=' . $i;
            }
        }

        $j = 1;

        foreach ($urls as $u) {
            $u = (substr($u, -1) == '&') ? substr($u, 0, -1) : $u;
            if (isset($_GET[$query])) {
                if ($_GET[$query] == $j) {
                    $output .= '<li class="active"><span class="active">' . $j . '</span></li>';
                } else {
                    $output .= '<li><a href="' . $u . '">' . $j . '</a></li>';
                }
            } else {
                $output .= ($j == 1) ? '<li class="active"><span class="active">' . $j . '</span></li>' : '<li><a href="' . $u . '">' . $j . '</a></li>';
            }

            $j++;
        }

        $output .= '</ul>' . "\n";
        return $output;
    }

    public static function li(array $list, array $attrAssoc = [])
    {

        $output = '';
        $attr = '';
        foreach ($attrAssoc as $key => $value) {
            $attr .= $key . '="' . $value . '" ';
        }

        foreach ($list as $li) {
            $output .= "\t" . '<li ' . $attr . '>' . $li . '</li>' . "\n";
        }

        return $output;
    }

    public static function checkbox(string $name, string $value, $checkMatch = NULL, $attrAssoc = [])
    {

        $output = "\t" . '<input type="checkbox" ';
        $output .= 'name="' . $name . '" ';
        $output .= 'value="' . $value . '" ';
        $output .= ($value == $checkMatch) ? 'checked ' : '';

        $attr = '';
        foreach ($attrAssoc as $key => $value) {
            $attr .= $key . '="' . $value . '" ';
        }

        $output .= $attr . ">\n";

        return $output;
    }

    public static function OptionsFromList(array $assoc, string $selectedVal = '', array $attrAssoc = []){

        $output = '';
        foreach($assoc as $val => $content){
            $output .= "\t".'<option value="'.$val.'">'.$content.'</option>'."\n";
        }

        return $output;
    }

}
