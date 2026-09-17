<?php
/**
 * ShuFeiCat 主题后台自定义表单元素
 *
 * ShuFei_Form_Element_PresetText —— 「选择 + 填写」混合输入框
 *   - 填写：普通文本输入，站长可直接填数值（14 / 14px / 1.2rem …）
 *   - 选择：<datalist> 提供原生下拉候选，同时渲染一排可点击的预设快捷标签
 *   - 点击标签由 assets/js/admin/theme-settings.js 接管（纯增强，JS 失效时输入框仍可用）
 *
 * 使用方式（在 functions.php 的 themeConfig 内）：
 *   new ShuFei_Form_Element_PresetText(
 *       'listRadius',
 *       array('8px' => '小 (8px)', '12px' => '标准 (12px)'),
 *       '12px',
 *       _t('列表卡片圆角'),
 *       _t('介绍：…'),
 *       '12px'
 *   );
 *
 * 可选：setNormalizer(callable) 在回显前归一化取值（如把旧枚举值 normal 显示为 12px），
 * 用于兼容老站点已保存的历史数据，参数中的默认值亦会经过归一化（需幂等）。
 *
 * 注意：构造函数签名与父类不同是刻意为之（PHP 不对构造函数做签名兼容检查），
 * 预设表必须在调用 parent::__construct() 之前赋值，因为父类构造时会调用 input()。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!class_exists('ShuFei_Form_Element_PresetText')) {
    class ShuFei_Form_Element_PresetText extends \Typecho\Widget\Helper\Form\Element\Text
    {
        /**
         * 预设项：值 => 展示名
         *
         * @var array
         */
        protected $presets = array();

        /**
         * 输入框 placeholder
         *
         * @var string
         */
        protected $placeholder = '';

        /**
         * 纯数字自动补齐的单位（为空表示不补，如「行数」「阴影」类字段）
         *
         * @var string
         */
        protected $unit = 'px';

        /**
         * 回显值归一化回调（可选）
         *
         * Typecho 在渲染主题设置表单时会用数据库中的原始值覆盖元素默认值
         * （见 Widget\Themes\Config::config()），因此老站点留存的旧枚举值
         * （normal / one / narrow …）会原样显示在输入框里。设置归一化回调后，
         * 显示前会先把原始值转换成前台真正生效的写法（12px / 2 / 200px …），
         * 站长保存一次即可完成数据迁移。
         *
         * @var callable|null
         */
        protected $normalizer = null;

        /**
         * @param string      $name        表单字段名
         * @param array       $presets     预设项 值 => 展示名
         * @param mixed       $value       默认值
         * @param string|null $label       标题
         * @param string|null $description 描述
         * @param string      $placeholder 输入框占位提示
         * @param string      $unit        纯数字自动补齐的单位（默认 px，传 '' 表示不补）
         */
        public function __construct(
            $name,
            array $presets = array(),
            $value = null,
            $label = null,
            $description = null,
            $placeholder = '',
            $unit = 'px'
        ) {
            $this->presets = $presets;
            $this->placeholder = (string) $placeholder;
            $this->unit = (string) $unit;

            parent::__construct($name, null, $value, $label, $description);
        }

        /**
         * 设置回显值归一化回调
         *
         * 回调签名：function ($rawValue) { return $normalizedValue; }
         * 注意：构造函数默认值与数据库值都会经过该回调，故回调需保证幂等。
         *
         * @param callable|null $normalizer
         * @return $this
         */
        public function setNormalizer($normalizer)
        {
            $this->normalizer = is_callable($normalizer) ? $normalizer : null;
            // 兼容先设默认值、后设回调的写法：立即用当前值重跑一次归一化
            if ($this->normalizer !== null && isset($this->value)) {
                $this->value($this->value);
            }
            return $this;
        }

        /**
         * 设置表单值（覆盖父类：写入前先做归一化）
         *
         * @param mixed $value 表单元素值
         * @return \Typecho\Widget\Helper\Form\Element
         */
        public function value($value): \Typecho\Widget\Helper\Form\Element
        {
            if ($this->normalizer !== null) {
                $value = call_user_func($this->normalizer, $value);
            }
            return parent::value($value);
        }

        /**
         * 初始化输入项：文本输入框 + 预设标签 + datalist
         *
         * @param string|null $name
         * @param array|null  $options
         * @return \Typecho\Widget\Helper\Layout|null
         */
        public function input(?string $name = null, ?array $options = null): ?\Typecho\Widget\Helper\Layout
        {
            $input = parent::input($name, $options);

            if ($this->placeholder !== '') {
                $input->setAttribute('placeholder', $this->placeholder);
            }

            if (empty($this->presets)) {
                return $input;
            }

            $safeName = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $name);
            $listId = 'shufei-preset-dl-' . $safeName;

            $input->setAttribute('list', $listId);
            $input->setAttribute('autocomplete', 'off');
            $input->setAttribute('data-preset-input', $safeName);
            $input->setAttribute('data-preset-unit', $this->unit);

            $datalist = '<datalist id="' . $listId . '">';
            $chips = '';
            foreach ($this->presets as $presetValue => $presetText) {
                $value = htmlspecialchars((string) $presetValue, ENT_QUOTES, 'UTF-8');
                $text = htmlspecialchars((string) $presetText, ENT_QUOTES, 'UTF-8');
                $datalist .= '<option value="' . $value . '"></option>';
                $chips .= '<a href="javascript:;" class="shufei-preset-chip"'
                    . ' data-preset-target="' . $safeName . '"'
                    . ' data-preset-value="' . $value . '"'
                    . ' title="点击填入 ' . $value . '">' . $text . '</a>';
            }
            $datalist .= '</datalist>';

            $wrap = new \Typecho\Widget\Helper\Layout('div', array('class' => 'shufei-preset-extra'));
            $wrap->html('<div class="shufei-preset-chips">' . $chips . '</div>' . $datalist);
            $this->container($wrap);

            return $input;
        }
    }
}
