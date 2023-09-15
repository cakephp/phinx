<?php
declare(strict_types=1);

namespace Phinx\Wrapper;

use Phinx\Console\PhinxApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

final class TextWrapperTest extends TestCase
{
    private const ANY_ENV_VALUE = 'something';

    /**
     * @dataProvider envValueProvider
     * @param string|null $env
     */
    public function testEnvTruthinessValues($env, array $options, array $expectedCommand): void
    {
        $app = $this->createMock(PhinxApplication::class);
        $app
            ->expects($this->once())
            ->method('doRun')
            ->with(new ArrayInput($expectedCommand))
            ->willReturn(0);

        $wrapper = new TextWrapper($app, $options);

        $wrapper->getStatus($env);
    }

    public function envValueProvider(): array
    {
        return [
            'env-value-only' => [
                self::ANY_ENV_VALUE,
                [],
                ['status', '-e' => self::ANY_ENV_VALUE],
            ],
            'options-env-value-only' => [
                null,
                ['environment' => self::ANY_ENV_VALUE],
                ['status', '-e' => self::ANY_ENV_VALUE],
            ],
            'both-values' => [
                self::ANY_ENV_VALUE,
                ['environment' => self::ANY_ENV_VALUE . 'additional'],
                ['status', '-e' => self::ANY_ENV_VALUE],
            ],
            'no-values' => [
                null,
                [],
                ['status'],
            ],
        ];
    }
}
