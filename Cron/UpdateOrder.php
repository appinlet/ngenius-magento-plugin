<?php

namespace NetworkInternational\NGenius\Cron;

/**
 * Class UpdateOrder
 */
class UpdateOrder extends \NetworkInternational\NGenius\Controller\NGeniusOnline\Payment
{
    /**
     * Default execute function.
     *
     * @return null
     */
    public function execute()
    {
        $this->state->emulateAreaCode(
            Area::AREA_FRONTEND,
                function () {
                    try {
                        $this->cronTask();
                        $this->logger->info('Cron Works');
                    } catch (\Exception $ex) {
                        $this->logger->error($ex->getMessage());
                    }
                }
        );
    }
}
