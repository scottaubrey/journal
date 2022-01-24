<?php

namespace test\eLife\Journal\Guzzle;

use eLife\Journal\Etoc\EarlyCareer;
use eLife\Journal\Etoc\ElifeNewsletter;
use eLife\Journal\Etoc\LatestArticles;
use eLife\Journal\Etoc\Newsletter;
use eLife\Journal\Etoc\Subscription;
use eLife\Journal\Etoc\Technology;
use eLife\Journal\Exception\CiviCrmResponseError;
use eLife\Journal\Guzzle\CiviCrmClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Traversable;

final class CiviCrmClientTest extends TestCase
{
    /**
     * @test
     */
    public function it_will_check_for_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_131' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
            new Response(200, [], json_encode(['values' => []])),
        ], $container);

        $checkSuccess = $client->checkSubscription('foo@bar.com');

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID, Technology::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                'email' => 'foo@bar.com',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_131',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());

        $checkFail = $client->checkSubscription('http://localhost/content-alerts/foo', false);

        $this->assertNull($checkFail->wait());
    }

    /**
     * @test
     */
    public function it_will_check_for_existing_user_by_preferences_url()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_131' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
        ], $container);

        $checkSuccess = $client->checkSubscription('http://localhost/content-alerts/foo', false);

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID, Technology::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                'custom_131' => 'http://localhost/content-alerts/foo',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_131',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());
    }

    /**
     * @test
     * @dataProvider providerNewsletterUnsubscribe
     */
    public function it_will_check_for_existing_user_by_unsubscribe_url(?Newsletter $newsletter, string $expectedUnsubscribeField)
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['values' => [
                [
                    'contact_id' => 12345,
                    'is_opt_out' => '0',
                    'email' => 'foo@bar.com',
                    'first_name' => '',
                    'last_name' => '',
                    'preferences' => [53,435],
                    'groups' => implode(',', [53,435]),
                    'custom_131' => 'http://localhost/content-alerts/foo',
                ],
            ]])),
        ], $container);

        $checkSuccess = $client->checkSubscription('http://localhost/content-alerts/foo', false, $newsletter);

        $this->assertEquals(new Subscription(
            12345,
            false,
            'foo@bar.com',
            '',
            '',
            [LatestArticles::GROUP_ID, Technology::GROUP_ID],
            'http://localhost/content-alerts/foo'
        ), $checkSuccess->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'get',
            'json' => [
                $expectedUnsubscribeField => 'http://localhost/content-alerts/foo',
                'return' => [
                    'group',
                    'first_name',
                    'last_name',
                    'email',
                    'is_opt_out',
                    'custom_131',
                ],
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('GET', $firstRequest->getMethod());
    }

    public function providerNewsletterUnsubscribe() : Traversable
    {
        yield 'null' => [null, 'custom_131'];
        yield 'default' => [new LatestArticles(), 'custom_132'];
        yield 'early-career' => [new EarlyCareer(), 'custom_133'];
        yield 'technology' => [new Technology(), 'custom_134'];
        yield 'elife-newsletter' => [new ElifeNewsletter(), 'custom_135'];
    }

    /**
     * @test
     */
    public function it_will_subscribe_a_new_user()
    {
        $container = [];

        $client = $this->prepareClient([
                new Response(200, [], json_encode(['id' => '12345'])),
                new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $subscribe = $client->subscribe('email@example.com', [new LatestArticles()], [], 'http://localhost/content-alerts/foo');

        $this->assertEquals([
            'contact_id' => '12345',
            'groups' => [
                'added' => ['latest_articles'],
                'removed' => [],
                'unchanged' => [],
            ],
        ], $subscribe->wait());

        $this->assertCount(2, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_type' => 'Individual',
                'email' => 'email@example.com',
                'first_name' => '',
                'last_name' => '',
                'custom_131' => 'http://localhost/content-alerts/foo',
                'is_opt_out' => 0,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Added',
                'group_id' => [
                    'All_Content_53',
                    'Journal_eToc_signup_1922',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_update_preferences_for_an_existing_user()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => '12345'])),
            new Response(200, [], json_encode(['is_error' => 0])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $subscribe = $client->subscribe('12345', [new LatestArticles(), new EarlyCareer()], [new LatestArticles('http://localhost/content-alerts/unsubscribe/foo'), new Technology('http://localhost/content-alerts/unsubscribe/foo/technology')], 'http://localhost/content-alerts/foo', null, 'New', 'Name', [new LatestArticles(), new Technology()]);

        $this->assertEquals([
            'contact_id' => '12345',
            'groups' => [
                'added' => ['early_career'],
                'removed' => ['technology'],
                'unchanged' => ['latest_articles'],
            ],
        ], $subscribe->wait());

        $this->assertCount(3, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_type' => 'Individual',
                'contact_id' => '12345',
                'first_name' => 'New',
                'last_name' => 'Name',
                'custom_131' => 'http://localhost/content-alerts/foo',
                'is_opt_out' => 0,
                'custom_132' => 'http://localhost/content-alerts/unsubscribe/foo',
                'custom_134' => 'http://localhost/content-alerts/unsubscribe/foo/technology',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Added',
                'group_id' => [
                    'early_careers_news_317',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());

        /** @var Request $thirdRequest */
        $thirdRequest = $container[2]['request'];
        $this->assertEquals('POST', $thirdRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'status' => 'Removed',
                'group_id' => [
                    'technology_news_435',
                ],
                'contact_id' => '12345',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $thirdRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_trigger_preferences_email()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $trigger = $client->triggerPreferencesEmail(12345);

        $this->assertSame([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(1, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_preferences_1923',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_will_trigger_preferences_email_setting_preferences_url()
    {
        $container = [];

        $client = $this->prepareClient([
            new Response(200, [], json_encode(['id' => 12345])),
            new Response(200, [], json_encode(['is_error' => 0])),
        ], $container);

        $trigger = $client->triggerPreferencesEmail(12345, 'http://localhost/content-alerts/new-preferences-url');

        $this->assertSame([
            'contact_id' => 12345,
        ], $trigger->wait());

        $this->assertCount(2, $container);

        /** @var Request $firstRequest */
        $firstRequest = $container[0]['request'];
        $this->assertEquals('POST', $firstRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'Contact',
            'action' => 'create',
            'json' => [
                'contact_id' => 12345,
                'custom_131' => 'http://localhost/content-alerts/new-preferences-url',
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $firstRequest->getUri()->getQuery());

        /** @var Request $secondRequest */
        $secondRequest = $container[1]['request'];
        $this->assertEquals('POST', $secondRequest->getMethod());
        $this->assertSame($this->prepareQuery([
            'entity' => 'GroupContact',
            'action' => 'create',
            'json' => [
                'group_id' => [
                    'Journal_eToc_preferences_1923',
                ],
                'contact_id' => 12345,
            ],
            'api_key' => 'api-key',
            'key' => 'site-key',
        ]), $secondRequest->getUri()->getQuery());
    }

    /**
     * @test
     */
    public function it_can_handle_error_from_civi()
    {
        $container = [];

        $client = $this->prepareClient([
            $firstError = new Response(200, [], json_encode(['is_error' => 1, 'error_message' => 'Error'])),
            new Response(200, [], json_encode(['id' => '23456'])),
            $secondError = new Response(200, [], json_encode(['is_error' => 1, 'error_message' => 'Error 2'])),
        ], $container);

        try {
            $client->subscribe('email@example.com', [new LatestArticles()], [], 'http://localhost/content-alerts/foo')->wait();
            $this->fail('CiviCrmResponseError was not thrown');
        } catch (CiviCrmResponseError $e) {
            $this->assertSame('Error', $e->getMessage());
            $this->assertSame($firstError, $e->getResponse());
        }

        try {
            $client->subscribe('email@example.com', [new LatestArticles(), new EarlyCareer()], [], 'http://localhost/content-alerts/foo')->wait();
            $this->fail('CiviCrmResponseError was not thrown');
        } catch (CiviCrmResponseError $e) {
            $this->assertSame('Error 2', $e->getMessage());
            $this->assertSame($secondError, $e->getResponse());
        }
    }

    private function prepareClient(array $queue = [], array &$container = []) : CiviCrmClient
    {
        $history = Middleware::history($container);

        $mock = new MockHandler($queue);

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        return new CiviCrmClient(new Client(['handler' => $handlerStack]), 'api-key', 'site-key');
    }

    private function prepareQuery(array $query) : string
    {
        return http_build_query(array_map(function ($value) {
            return is_array($value) ? json_encode($value) : $value;
        }, $query));
    }
}
