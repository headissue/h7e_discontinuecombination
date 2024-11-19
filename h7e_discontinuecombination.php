<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class H7e_DiscontinueCombination extends Module {

    public function __construct() {
        $this->name = 'h7e_discontinuecombination';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'headissue GmbH - Jens Wilke';
        $this->need_instance = 0;

        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];

        parent::__construct();

        $this->displayName = $this->l('Discontinue product combination');
        $this->description = $this->l(
            'When the MPN of a product combination is ending in "#" and has quantity of '
            .'0 this combination is removed from the options on the product page');
    }

    public function install(): bool {
        return parent::install() &&
           $this->registerHook('actionFrontControllerSetVariables');
    }

    /**
     * Hook position: This is the first hook called after ProductController->assignAttributesGroups
     * which fills the template variables with attribute groups and combinations.
     *
     * Strategy: First iterate through the combinations and remove combinations marked as discontinued and
     * no stock quantity. While doing so we keep track of used attributes. Next, we remove the unused attributes
     * from groups, colours and images.
     *
     * @param $params
     * @return void
     */
    public function hookActionFrontControllerSetVariables(& $params): void {
        $combinations = $this->context->smarty->getTemplateVars('combinations');
        $groups = $this->context->smarty->getTemplateVars('groups');
        // not a product page with combinations, exit
        if (!$combinations || !$groups) {
            return;
        }
        $colors = $this->context->smarty->getTemplateVars('colors');
        $combinationImages = $this->context->smarty->getTemplateVars('combinationImages');
        // remove combinations / variants with 0 stock and MPN with discontinued marker
        $productAttributes = $this->context->smarty->getTemplateVars('product')['attributes'];
        $id_color = 0;
        foreach ($productAttributes as $key => $val) {
            $id = $val['id_attribute'];
            if (isset($colors[$id])) {
                $id_color =  $id;
            }
        }
        $usedAttributes = [];
        $attributesToRemove = [];
        $discoCombo = false;
        foreach ($combinations as $key => $combo) {
            if (str_ends_with($combo['mpn'], '#') && $combo['quantity'] == 0) {
                // if discontinued, remove the combination
                unset($combinations[$key]);
                $discoCombo = true;
                // if there is a colour, and the colour of this combo is the colour
                // of the product selected, remove remove every attribute except the colors.
                // Example: Grey and Black has Size S, M, L. If Grey/S is discontinued and out of stock, the size
                // S needs to be removed. However, if Black is selected, we still want to show size S.
                if ($colors && in_array($id_color, $combo['attributes'])) {
                    foreach ($combo['attributes'] as $idx => $id) {
                        if (!isset($colors[$id])) {
                            $attributesToRemove[$id] = 1;
                        }
                    }
                }
            } else {
                foreach ($combo['attributes'] as $k => $attribute_id) {
                    $usedAttributes[$attribute_id] = 1;
                }
            }
        }
        // nothing discontinued, pass unmodified
        if (!$discoCombo) {
            return;
        }
        // remove attributes to remove from the set of used attributes, so we don't need to lookup
        // two structures.
        foreach ($attributesToRemove as $key => $value) {
            unset($usedAttributes[$key]);
        }
        if ($colors) {
            // remove unused color
            foreach ($colors as $id => & $value) {
                if (!isset($usedAttributes[$id])) {
                    unset($colors[$id]);
                }
            }
        }
        // remove unused image
        if ($combinationImages) {
            foreach ($colors as $id => & $value) {
                if (!isset($usedAttributes[$id])) {
                    unset($combinationImages[$id]);
                }
            }
        }
        // remove unused attributes and attribute groups
        foreach ($groups as $groupId => & $group) {
            foreach ($group['attributes'] as $attributeId => $attribute) {
                if (!isset($usedAttributes[$attributeId])) {
                    // unset($groups[$groupId]['attributes'][$attributeId]);
                    unset($group['attributes'][$attributeId]);
                }
            }
            if (!$group['attributes']) {
                unset($groups[$groupId]);
            } else {
                // fix default, if just removed it
                if (!isset($group['attributes'][$group['default']])) {
                    $group['default'] = min(array_keys($group['attributes']));
                }
            }
        }
        // reassign template variables with changes
        $params['templateVars']['combinations'] = $combinations;
        $params['templateVars']['groups'] = $groups;
        if ($colors) {
            $params['templateVars']['colors'] = $colors;
        }
        if ($combinationImages) {
            $params['templateVars']['combinationImages'] = $combinationImages;
        }
    }

}
