<?php

use Alchemy\Phrasea\Border\File;

/**
 * @group functional
 * @group legacy
 */
class media_subdefTest extends \PhraseanetTestCase
{
    /**
     * @var \media_subdef
     */
    private static $objectPresent;

    /**
     * @var \media_subdef
     */
    private static $objectNotPresent;

    /**
     * @var \record_adapter
     */
    private static $recordonbleu;

    public function setUp()
    {
        parent::setUp();

        if (null === self::$recordonbleu) {
            $app = $this->getApplication();
            $file = new File($app, $app['mediavorus']->guess(__DIR__ . "/../../files/iphone_pic.jpg"), self::$DI['collection']);

            self::$recordonbleu = record_adapter::createFromFile($file, $app);
            $app['subdef.generator']->generateSubdefs(self::$recordonbleu);

            foreach (self::$recordonbleu->get_subdefs() as $subdef) {
                if (!in_array($subdef->get_name(), ['thumbnail', 'preview'], true)) {
                    continue;
                }

                if (! self::$objectPresent) {
                    self::$objectPresent = $subdef;
                    continue;
                }
                if (! self::$objectNotPresent) {
                    self::$objectNotPresent = $subdef;
                    continue;
                }
            }

            self::$objectNotPresent->remove_file();
        }
    }

    public static function tearDownAfterClass()
    {
        self::$objectPresent = self::$objectNotPresent = self::$recordonbleu = null;
        parent::tearDownAfterClass();
    }

    /**
     * @covers media_subdef::is_physically_present
     */
    public function testIs_physically_present()
    {
        $this->assertTrue(self::$objectPresent->is_physically_present());
        $this->assertFalse(self::$objectNotPresent->is_physically_present());
    }

    /**
     * @covers media_subdef::is_physically_present
     */
    public function testStoryIsNotPhysicallyPresent()
    {
        $this->assertFalse(self::$DI['record_story_3']->get_subdef('thumbnail')->is_physically_present());
    }

    /**
     * @covers media_subdef::get_record
     */
    public function testGet_record()
    {
        $this->assertEquals(self::$recordonbleu->getRecordId(), self::$objectNotPresent->get_record()->getRecordId());
        $this->assertEquals(self::$recordonbleu->getRecordId(), self::$objectPresent->get_record()->getRecordId());
        $this->assertEquals(self::$recordonbleu->getDataboxId(), self::$objectNotPresent->get_record()->getDataboxId());
        $this->assertEquals(self::$recordonbleu->getDataboxId(), self::$objectPresent->get_record()->getDataboxId());
    }

    /**
     * @covers media_subdef::get_url
     */
    public function testGet_url()
    {
        $this->assertInstanceOf('Guzzle\Http\Url', self::$objectNotPresent->get_url());
        $this->assertInstanceOf('Guzzle\Http\Url', self::$objectPresent->get_url());
        $this->assertEquals('/assets/common/images/icons/substitution/image_jpeg.png', (string) self::$objectNotPresent->get_url());
        $this->assertRegExp('#\/datafiles\/' . self::$objectPresent->get_sbas_id() . '\/' . self::$objectPresent->get_record_id() . '\/preview\/\?etag=[0-9a-f]{32}#', (string) self::$objectPresent->get_url());
    }

    /**
     * @covers media_subdef::get_permalink
     */
    public function testGet_permalink()
    {
        $this->assertInstanceOf('\\media_Permalink_adapter', self::$objectNotPresent->get_permalink());
        $this->assertInstanceOf('\\media_Permalink_adapter', self::$objectPresent->get_permalink());
    }

    /**
     * @covers media_subdef::get_record_id
     */
    public function testGet_record_id()
    {
        $this->assertEquals(self::$recordonbleu->getRecordId(), self::$objectNotPresent->get_record()->getRecordId());
        $this->assertEquals(self::$recordonbleu->getRecordId(), self::$objectPresent->get_record()->getRecordId());
    }

    /**
     * @covers media_subdef::getEtag
     */
    public function testGetEtag()
    {
        $this->assertNull(self::$objectNotPresent->getEtag());
        $this->assertInternalType('string', self::$objectPresent->getEtag());
        $this->assertRegExp('/[a-zA-Z0-9]{32}/', self::$objectPresent->getEtag());
    }

    /**
     * @covers media_subdef::setEtag
     */
    public function testSetEtag()
    {
        $etag = md5('random');
        self::$objectNotPresent->setEtag($etag);
        $this->assertEquals($etag, self::$objectNotPresent->getEtag());
    }

    /**
     * @covers media_subdef::get_sbas_id
     */
    public function testGet_sbas_id()
    {
        $this->assertEquals(self::$recordonbleu->getDataboxId(), self::$objectNotPresent->get_record()->getDataboxId());
        $this->assertEquals(self::$recordonbleu->getDataboxId(), self::$objectPresent->get_record()->getDataboxId());
    }

    /**
     * @covers media_subdef::get_type
     */
    public function testGet_type()
    {
        $this->assertEquals(\media_subdef::TYPE_IMAGE, self::$objectPresent->get_type());
    }

    /**
     * @covers media_subdef::get_mime
     */
    public function testGet_mime()
    {
        $this->assertEquals('image/jpeg', self::$objectPresent->get_mime());
        $this->assertEquals('image/png', self::$objectNotPresent->get_mime());
    }

    /**
     * @covers media_subdef::get_path
     */
    public function testGet_path()
    {
        $this->assertEquals(dirname(self::$objectPresent->getRealPath()) . DIRECTORY_SEPARATOR, self::$objectPresent->get_path());
        $this->assertEquals(dirname(self::$objectNotPresent->getRealPath()) . DIRECTORY_SEPARATOR, self::$objectNotPresent->get_path());
    }

    /**
     * @covers media_subdef::get_file
     */
    public function testGet_file()
    {
        $this->assertEquals(basename(self::$objectPresent->getRealPath()), self::$objectPresent->get_file());
        $this->assertEquals(basename(self::$objectNotPresent->getRealPath()), self::$objectNotPresent->get_file());
    }

    /**
     * @covers media_subdef::get_size
     */
    public function testGet_size()
    {
        $this->assertTrue(self::$objectPresent->get_size() > 0);
        $this->assertTrue(self::$objectNotPresent->get_size() > 0);
    }

    /**
     * @covers media_subdef::get_name
     */
    public function testGet_name()
    {
        $this->assertTrue(in_array(self::$objectPresent->get_name(), ['thumbnail', 'preview']));
        $this->assertTrue(in_array(self::$objectNotPresent->get_name(), ['thumbnail', 'preview']));
    }

    /**
     * @covers media_subdef::get_subdef_id
     */
    public function testGet_subdef_id()
    {
        $this->assertInternalType('int', self::$objectPresent->get_subdef_id());
        $this->assertInternalType('int', self::$objectNotPresent->get_subdef_id());
        $this->assertTrue(self::$objectPresent->get_size() > 0);
        $this->assertTrue(self::$objectNotPresent->get_size() > 0);
    }

    /**
     * @covers media_subdef::is_substituted
     */
    public function testIs_substituted()
    {
        $this->assertFalse(self::$objectPresent->is_substituted());
        $this->assertFalse(self::$objectNotPresent->is_substituted());
    }

    /**
     * @covers media_subdef::getRealPath
     */
    public function testGetRealPath()
    {
        $this->assertEquals(self::$objectPresent->get_path() . self::$objectPresent->get_file(), self::$objectPresent->getRealPath());
        $this->assertEquals(self::$objectNotPresent->get_path() . self::$objectNotPresent->get_file(), self::$objectNotPresent->getRealPath());
        $this->assertTrue(file_exists(self::$objectPresent->getRealPath()));
        $this->assertTrue(file_exists(self::$objectNotPresent->getRealPath()));
        $this->assertTrue(is_readable(self::$objectPresent->getRealPath()));
        $this->assertTrue(is_readable(self::$objectNotPresent->getRealPath()));
        $this->assertTrue(is_writable(self::$objectPresent->getRealPath()));
        $this->assertTrue(is_writable(self::$objectNotPresent->getRealPath()));
    }

    /**
     * @covers media_subdef::get_modification_date
     */
    public function testGet_modification_date()
    {
        $this->assertInstanceOf('\\DateTime', self::$objectPresent->get_modification_date());
        $this->assertInstanceOf('\\DateTime', self::$objectNotPresent->get_modification_date());
    }

    /**
     * @covers media_subdef::get_creation_date
     */
    public function testGet_creation_date()
    {
        $this->assertInstanceOf('\\DateTime', self::$objectPresent->get_creation_date());
        $this->assertInstanceOf('\\DateTime', self::$objectNotPresent->get_creation_date());
    }

    /**
     * @covers media_subdef::renew_url
     */
    public function testRenew_url()
    {
        $this->assertInstanceOf('Guzzle\Http\Url', self::$objectPresent->renew_url());
        $this->assertInstanceOf('Guzzle\Http\Url', self::$objectNotPresent->renew_url());
    }

    /**
     * @covers media_subdef::getDataboxSubdef
     */
    public function testGetDataboxSubdef()
    {
        $this->assertInstanceOf('\\databox_subdef', self::$objectPresent->getDataboxSubdef());
        $this->assertInstanceOf('\\databox_subdef', self::$objectNotPresent->getDataboxSubdef());
    }

    /**
     * @covers media_subdef::rotate
     */
    public function testRotate()
    {
        $width_before = self::$objectPresent->get_width();
        $height_before = self::$objectPresent->get_height();

        self::$objectPresent->rotate(90, self::$DI['app']['media-alchemyst'], self::$DI['app']['mediavorus']);

        // because rotate may cause round errors we check with +-1?

        $this->assertGreaterThanOrEqual($width_before-1, self::$objectPresent->get_height());
        $this->assertLessThanOrEqual($width_before+1, self::$objectPresent->get_height());

        $this->assertGreaterThanOrEqual($height_before-1, self::$objectPresent->get_width());
        $this->assertLessThanOrEqual($height_before+1, self::$objectPresent->get_width());
    }

    /**
     * @covers media_subdef::rotate
     * @expectedException \Alchemy\Phrasea\Exception\RuntimeException
     * @covers \Alchemy\Phrasea\Exception\RuntimeException
     */
    public function testRotateOnSubstitution()
    {
        self::$objectNotPresent->rotate(90, self::$DI['app']['media-alchemyst'], self::$DI['app']['mediavorus']);
    }

    /**
     * @covers media_subdef::readTechnicalDatas
     */
    public function testReadTechnicalDatas()
    {
        $technical_datas = self::$objectPresent->readTechnicalDatas(self::$DI['app']['mediavorus']);
        $this->assertArrayHasKey(media_subdef::TC_DATA_WIDTH, $technical_datas);
        $this->assertArrayHasKey(media_subdef::TC_DATA_HEIGHT, $technical_datas);
        $this->assertArrayHasKey(media_subdef::TC_DATA_CHANNELS, $technical_datas);
        $this->assertArrayHasKey(media_subdef::TC_DATA_COLORDEPTH, $technical_datas);
        $this->assertArrayHasKey(media_subdef::TC_DATA_MIMETYPE, $technical_datas);
        $this->assertArrayHasKey(media_subdef::TC_DATA_FILESIZE, $technical_datas);

        $technical_datas = self::$objectNotPresent->readTechnicalDatas(self::$DI['app']['mediavorus']);
        $this->assertEquals([], $technical_datas);
    }
}
