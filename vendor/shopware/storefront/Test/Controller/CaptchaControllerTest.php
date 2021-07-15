<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\CaptchaController;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPagelet;

class CaptchaControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private CaptchaController $controller;

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = $this->getContainer()->get(CaptchaController::class);
    }

    public function testLoadBasicCaptchaContent(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());

        $browser->request('GET', $_SERVER['APP_URL'] . '/basic-captcha');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertInstanceOf(BasicCaptchaPagelet::class, $response->getData()['page']);
    }

    public function testValidateCaptcha(): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $formId = 'Kyln-test';
        $basicCaptchaSession = 'kylnsession';
        $this->getContainer()->get('session')->set($formId . 'basic_captcha_session', 'kylnsession');

        $token = $this->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('frontend.captcha.basic-captcha.validate')
            ->getValue();
        $payload = [
            'formId' => $formId,
            'shopware_basic_captcha_confirm' => $basicCaptchaSession,
            '_csrf_token' => $token,
        ];

        // Basic Captcha Valid
        $browser->request('POST', $_SERVER['APP_URL'] . '/basic-captcha-validate', $payload);
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('session', json_decode($response->getContent(), true));

        // BasicCaptcha Invalid
        $this->getContainer()->get('session')->set($formId . 'basic_captcha_session', 'invalid');
        $browser->request('POST', $_SERVER['APP_URL'] . '/basic-captcha-validate', $payload);
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('error', json_decode($response->getContent(), true)[0]);
    }
}
