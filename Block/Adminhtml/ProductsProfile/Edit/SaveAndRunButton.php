<?php

declare(strict_types=1);

namespace Nx6\PedidosYa\Block\Adminhtml\ProductsProfile\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Saves the products profile, then runs its export in the same request.
 *
 * Submits the form through its regular save action with an extra save_and_run=1 field, which
 * Products\Profile\Save picks up after a successful save.
 */
class SaveAndRunButton extends GenericButton implements ButtonProviderInterface
{
    #[\Override]
    public function getButtonData(): array
    {
        return [
            'label' => __('Save and Run'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'nx6_pedidosya_products_profile_form.nx6_pedidosya_products_profile_form',
                                'actionName' => 'save',
                                'params' => [true, ['save_and_run' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 85,
        ];
    }
}
