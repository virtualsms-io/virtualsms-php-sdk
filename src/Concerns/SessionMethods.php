<?php

namespace VirtualSMS\Concerns;

use VirtualSMS\Exceptions\ApiException;
use VirtualSMS\Exceptions\NotFoundException;
use VirtualSMS\Exceptions\ServerErrorException;
use VirtualSMS\Exceptions\VirtualSMSException;

/**
 * Session (1 in-scope method): start a country-matched cloud browser session
 * the caller drives manually via viewer_url. Beta, invite-only. The 3
 * session-*drive* tools (navigate/stop/session_viewer) are gated on the MCP
 * surface behind VIRTUALSMS_ENABLE_SESSIONS and are explicitly out of scope
 * for v2.0.0 SDKs.
 */
trait SessionMethods
{
    /**
     * @param array{
     *   service_name?: string,
     *   country?: string,
     *   device_mode?: string,
     *   with_proxy?: bool,
     *   target_url?: string,
     *   order_id?: string,
     *   mode?: string
     * } $params
     */
    public function start_manual_registration_session(array $params = []): array
    {
        $this->requireApiKey();
        $withProxy = $params['with_proxy'] ?? isset($params['country']);

        try {
            $res = $this->request('POST', '/api/v1/browser-sessions/start', [], [
                'serviceName' => $params['service_name'] ?? null,
                'country' => $params['country'] ?? null,
                'deviceMode' => $params['device_mode'] ?? null,
                'withProxy' => $withProxy,
                'targetUrl' => $params['target_url'] ?? null,
                'orderId' => $params['order_id'] ?? null,
                'mode' => $params['mode'] ?? 'fresh',
            ]);
        } catch (VirtualSMSException $e) {
            if ($this->isSessionsUnavailableError($e)) {
                throw new VirtualSMSException(
                    'Browser sessions are in invite-only beta. Join https://t.me/VirtualSMS_io for beta access and updates.'
                );
            }
            throw $e;
        }

        return $res['session'] ?? $res;
    }

    /**
     * Beta-gate signals only: endpoint missing (404), feature flag off (503),
     * or account not allowlisted (403). A 500, 401, or 402 is a real error
     * and keeps its own message.
     */
    private function isSessionsUnavailableError(VirtualSMSException $e): bool
    {
        if ($e instanceof NotFoundException) {
            return true;
        }
        if ($e instanceof ApiException && $e->getStatusCode() === 403) {
            return true;
        }
        if ($e instanceof ServerErrorException && strpos($e->getMessage(), '(503)') !== false) {
            return true;
        }
        return false;
    }
}
