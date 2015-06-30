<?php

namespace Alchemy\Tests\Phrasea\Controller\Prod;

use Alchemy\Phrasea\Border\File;
use Alchemy\Phrasea\SearchEngine\SearchEngineOptions;
use Alchemy\Phrasea\Model\Entities\Basket;
use Alchemy\Phrasea\Model\Entities\BasketElement;

/**
 * @group functional
 * @group legacy
 * @group authenticated
 * @group web
 * @todo Test Alchemy\Phrasea\Controller\Prod\Export::exportMail
 */
class RecordsTest extends \PhraseanetAuthenticatedWebTestCase
{
    protected $client;

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::whatCanIDelete
     */
    public function testWhatCanIDelete()
    {
        self::$DI['client']->request('POST', '/prod/records/delete/what/', ['lst'     => self::$DI['record_1']->get_serialize_key()]);
        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isOk());
        unset($response);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::doDeleteRecords
     */
    public function testDoDeleteRecords()
    {
        $file = new File(self::$DI['app'], self::$DI['app']['mediavorus']->guess(__DIR__ . '/../../../../../files/cestlafete.jpg'), self::$DI['collection']);
        $record = \record_adapter::createFromFile($file, self::$DI['app']);
        $this->XMLHTTPRequest('POST', '/prod/records/delete/', ['lst'     => $record->get_serialize_key()]);
        $response = self::$DI['client']->getResponse();
        $datas = (array) json_decode($response->getContent());
        $this->assertContains($record->get_serialize_key(), $datas);
        try {
            new \record_adapter(self::$DI['app'], $record->get_sbas_id(), $record->get_record_id());
            $this->fail('Record not deleted');
        } catch (\Exception $e) {

        }
        unset($response, $datas, $record);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::renewUrl
     */
    public function testRenewUrl()
    {
        $file = new File(self::$DI['app'], self::$DI['app']['mediavorus']->guess(__DIR__ . '/../../../../../files/cestlafete.jpg'), self::$DI['collection']);
        $record = \record_adapter::createFromFile($file, self::$DI['app']);
        $this->XMLHTTPRequest('POST', '/prod/records/renew-url/', ['lst'     => $record->get_serialize_key()]);
        $response = self::$DI['client']->getResponse();
        $datas = (array) json_decode($response->getContent());
        $this->assertTrue(count($datas) > 0);
        $record->delete();
        unset($response, $datas, $record);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailNotAjax()
    {
        self::$DI['client']->request('POST', '/prod/records/');

        $this->assertBadResponse(self::$DI['client']->getResponse());
    }

    public function testGetRecordDetailAsGuest()
    {
        $this->authenticate(self::$DI['app'], self::$DI['user_guest']);

        $basket = new Basket();
        $basket->setUser(self::$DI['user_guest']);
        $basket->setName('test');

        self::$DI['app']['orm.em']->persist($basket);

        $element = new BasketElement();
        $element->setRecord(self::$DI['record_1']);
        $element->setBasket($basket);
        $basket->addElement($element);

        self::$DI['app']['orm.em']->persist($element);
        self::$DI['app']['orm.em']->flush();

        $this->XMLHTTPRequest('POST', '/prod/records/', [
            'env'            => 'BASK',
            'pos'            => 0,
            'query'          => '',
            'cont'           => $basket->getId(),
        ]);
        $response = self::$DI['client']->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('desc', $data);
        $this->assertArrayHasKey('html_preview', $data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('others', $data);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('popularity', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertArrayHasKey('pos', $data);
        $this->assertArrayHasKey('title', $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailResult()
    {
        $this->authenticate(self::$DI['app']);
        self::$DI['record_1'];

        $options = new SearchEngineOptions();
        $acl = self::$DI['app']->getAclForUser(self::$DI['app']->getAuthenticatedUser());
        $options->onCollections($acl->get_granted_base());
        $serializedOptions = $options->serialize();

        $this->XMLHTTPRequest('POST', '/prod/records/', [
            'env'            => 'RESULT',
            'options_serial' => $serializedOptions,
            'pos'            => 0,
            'query'          => ''
        ]);

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('desc', $data);
        $this->assertArrayHasKey('html_preview', $data);
        $this->assertArrayHasKey('current', $data);
        $this->assertArrayHasKey('others', $data);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('popularity', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertArrayHasKey('pos', $data);
        $this->assertArrayHasKey('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailREG()
    {
        $this->authenticate(self::$DI['app']);
        self::$DI['record_story_1'];

        $this->XMLHTTPRequest('POST', '/prod/records/', [
            'env'   => 'REG',
            'pos'   => 0,
            'query' => '',
            'cont'  =>   self::$DI['record_story_1']->get_serialize_key()
        ]);

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());
        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailBasket()
    {
        $this->authenticate(self::$DI['app']);
        $basket = self::$DI['app']['orm.em']->find('Phraseanet:Basket', 1);

        $this->XMLHTTPRequest('POST', '/prod/records/', [
            'env'   => 'BASK',
            'pos'   => 0,
            'query' => '',
            'cont'  => $basket->getId()
        ]);

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());

        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }

    /**
     * @covers Alchemy\Phrasea\Controller\Prod\Records::getRecord
     */
    public function testGetRecordDetailFeed()
    {
        $this->authenticate(self::$DI['app']);

        self::$DI['app']['notification.deliverer'] = $this->getMockBuilder('Alchemy\Phrasea\Notification\Deliverer')
            ->disableOriginalConstructor()
            ->getMock();

        $feed = self::$DI['app']['orm.em']->find('Phraseanet:Feed', 1);
        $feedEntry = $feed->getEntries()->first();

        $this->XMLHTTPRequest('POST', '/prod/records/', [
            'env'   => 'FEED',
            'pos'   => 0,
            'query' => '',
            'cont'  => $feedEntry->getId()
        ]);

        $response = self::$DI['client']->getResponse();
        $data = json_decode($response->getContent());
        $this->assertObjectHasAttribute('desc', $data);
        $this->assertObjectHasAttribute('html_preview', $data);
        $this->assertObjectHasAttribute('current', $data);
        $this->assertObjectHasAttribute('others', $data);
        $this->assertObjectHasAttribute('history', $data);
        $this->assertObjectHasAttribute('popularity', $data);
        $this->assertObjectHasAttribute('tools', $data);
        $this->assertObjectHasAttribute('pos', $data);
        $this->assertObjectHasAttribute('title', $data);

        unset($response, $data);
    }
}
