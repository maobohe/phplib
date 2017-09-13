<?php
/**
 * 根据字典数据生成表单
 */
namespace Lib\Html;
class Form
{
    /**
     * 生成 可以跳转的 radio
     * @param $definitionList
     * @param $fieldName
     * @param null $selectedValue
     * @param array $removeParaArr
     * @return string
     */
    static function radioHref($definitionList, $fieldName, $removeParaArr = array())
    {
        $html = '';
        foreach ($definitionList as $definition_value => $definition_name) {
            $checked = '';
            if (isset($_REQUEST[$fieldName]) && $_REQUEST [$fieldName] == $definition_value) {
                $checked = 'checked="checked"';
            }

            $className = !empty($fieldName) ? 'class="' . $fieldName . '_' . $definition_value . '"' : '';
            $removeParaJsArr = !empty($removeParaArr) && is_array($removeParaArr) ? "['" . implode("','", $removeParaArr) . "']" : "[]";
            $html .= '<label ' . $className . '><input type="radio" name="' . $fieldName . '" value="' . $definition_value . '" onclick="goLocation({\'' . $fieldName . '\': \'' . $definition_value . '\'},' . $removeParaJsArr . ');"  ' . $checked . '/><span>' . $definition_name . '</span></label>';
        }
        $htmlAllChecked = '';
        if (!isset($_REQUEST [$fieldName])) {
            $htmlAllChecked = 'checked="checked"';
        }
        $htmlAll = '<label ' . $className . '><input type="radio" name="' . $fieldName . '" onclick="goLocation({},[\'' . $fieldName . '\']);"  ' . $htmlAllChecked . '/><span>全部</span></label>';
        return $htmlAll . $html;
    }

    static function radio($definitionList, $fieldName, $selectedValue = null, $onclick = null)
    {
        $html = '';
        foreach ($definitionList as $definition_value => $definition_name) {
            $checked = '';
            if (isset($_REQUEST [$fieldName]) && $_REQUEST [$fieldName] == $definition_value) {
                $checked = 'checked="checked"';
            } elseif
            ($selectedValue == $definition_value
            ) {
                $checked = 'checked="checked"';
            }

            $className = !empty($fieldName) ? 'class="' . $fieldName . '_' . $definition_value . '"' : '';
            $removeParaJsArr = !empty($removeParaArr) && is_array($removeParaArr) ? "['" . implode("','", $removeParaArr) . "']" : "[]";
            $html .= '<label ' . $className . '><input type="radio" name="' . $fieldName . '" value="' . $definition_value . '" onclick="' . $onclick . '"  ' . $checked . '/><span>' . $definition_name . '</span></label>';
        }
        return $html;
    }

    /**
     * 生成 <select></select>
     * @param $definitionList
     * @param $fieldName
     * @param null $selectedValue
     * @param null $nodeExtra
     * @return string
     */
    static function select($definitionList, $fieldName, $selectedValue = null, $nodeExtra = null)
    {
        $html = '<select name="' . $fieldName . '" ' . $nodeExtra . ' class="form-control" >';
        foreach ($definitionList as $definition_value => $definition_name) {
            $selected = isset($selectedValue) && $selectedValue == $definition_value ? 'selected="selected"' : '';
            $html .= '<option value="' . $definition_value . '" ' . $selected . '/>' . (!empty($definition_name) && $definition_name == 'all' && empty($definition_value) ? '请选择' : $definition_name) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    static function selectHref($definitionList, $fieldName, $nodeExtra = null)
    {
        $html = '<select name="' . $fieldName . '" ' . $nodeExtra . ' class="form-control" >';


        $htmlAllChecked = '';
        if (!isset($_REQUEST [$fieldName])) {
            $htmlAllChecked = 'checked="checked"';
        }
        $htmlAll = '<option value="_selectHref_remove_">请选择</option>';
        $html .= $htmlAll;

        foreach ($definitionList as $definition_value => $definition_name) {
            $selected = '';
            if (isset($_REQUEST[$fieldName]) && $_REQUEST [$fieldName] == $definition_value) {
                $selected = 'selected="selected"';
            }
            $html .= '<option value="' . $definition_value . '" ' . $selected . '/>' . (!empty($definition_name) && $definition_name == 'all' && empty($definition_value) ? '请选择' : $definition_name) . '</option>';
        }


        $html .= '</select>';
        $html .= <<<EOT
        <script>
        $('select[name=$fieldName]').change(function () {
            var type_id = $(this).val();
            if(type_id=='_selectHref_remove_'){
                goLocation({},['$fieldName']);
            }
            else{
                goLocation({type_id: type_id});
            }
        });
        </script>
EOT;
        return $html;
    }
}
