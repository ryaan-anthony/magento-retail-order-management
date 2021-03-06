<?php
/**
 * Copyright (c) 2013-2014 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright   Copyright (c) 2013-2014 eBay Enterprise, Inc. (http://www.ebayenterprise.com/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class EbayEnterprise_Catalog_Test_Model_Feed_CoreTest extends EbayEnterprise_Eb2cCore_Test_Base
{
    /**
     * Get a mock Varien_Io_File object to sub out for a real one.
     * @return Mock_Varien_File_Io
     */
    protected function _getMockFsTool()
    {
        return $this->getMockBuilder('Varien_Io_File', array('__destruct'));
    }
    /**
     * Test _construct method
     */
    public function testConstruct()
    {
        $fileMock = $this->getMock('Varien_Io_File', array('setAllowCreateFolders', 'open'));
        $fileMock->expects($this->once())
            ->method('setAllowCreateFolders')
            ->with($this->equalTo(true))
            ->will($this->returnSelf());
        $fileMock->expects($this->once())
            ->method('open')
            ->will($this->returnSelf());

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('hasFsTool', 'setFsTool', 'getFsTool', '_validateFeedConfig', '_setUpDirs'))
            ->getMock();
        $feedModelMock->expects($this->once())
            ->method('hasFsTool')
            ->will($this->returnValue(false));
        $feedModelMock->expects($this->once())
            ->method('setFsTool')
            ->with($this->isInstanceOf('Varien_Io_File'))
            ->will($this->returnSelf());
        $feedModelMock->expects($this->once())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));
        $feedModelMock->expects($this->once())
            ->method('_validateFeedConfig')
            ->will($this->returnSelf());
        $feedModelMock->expects($this->once())
            ->method('_setUpDirs')
            ->will($this->returnSelf());

        EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_construct');
    }
    /**
     * Constructing an instance of the core feed model with invalid config should
     * thrown an exception.
     */
    public function testConstructInvalidConfig()
    {
        $fileMock = $this->getMock('Varien_Io_File', array('setAllowCreateFolders', 'open'));
        // if the feed config is invalid, no reason to set up the FS tool
        $fileMock->expects($this->never())
            ->method('setAllowCreateFolders');

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('hasFsTool', 'getFsTool', '_validateFeedConfig', '_setUpDirs'))
            ->getMock();
        $feedModelMock->expects($this->any())
            ->method('hasFsTool')
            ->will($this->returnValue(true));
        $feedModelMock->expects($this->any())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));
        $feedModelMock->expects($this->once())
            ->method('_validateFeedConfig')
            ->will($this->throwException(new EbayEnterprise_Catalog_Exception_Feed_File()));
        $feedModelMock->expects($this->never())
            ->method('_setUpDirs');

        $this->setExpectedException('EbayEnterprise_Catalog_Exception_Feed_File');
        EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_construct');
    }
    /**
     * Provide a set of feed configuration and, if the config is invalid, the
     * exception message expected.
     * @return array
     */
    public function provideFeedConfigForValidation()
    {
        return array(
            array(array('local_directory' => 'some/local', 'event_type' => 'ItemMaster'), null),
            array(array(), "EbayEnterprise_Catalog_Model_Feed_Core missing configuration: 'local_directory', 'event_type'."),
            array('this is not right', "EbayEnterprise_Catalog_Model_Feed_Core 'feed_config' must be an array of feed configuration values.")
        );
    }
    /**
     * Test validation of the feed config. Provider will give a set of feed config
     * and if the config is expected to be invalid, the exception message that
     * should be caught.
     * @param  array  $feedConfig
     * @param  string $exceptionMessage
     * @dataProvider provideFeedConfigForValidation
     */
    public function testValidateFeedConfig($feedConfig, $exceptionMessage)
    {
        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $feedModelMock->setFeedConfig($feedConfig);
        // ensure the list of required config fields are set to an expected set of fields
        EcomDev_Utils_Reflection::setRestrictedPropertyValue($feedModelMock, '_requiredConfigFields', array('local_directory', 'event_type'));

        if ($exceptionMessage) {
            $this->setExpectedException('EbayEnterprise_Catalog_Exception_Feed_File', $exceptionMessage);
            EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_validateFeedConfig');
        } else {
            $this->assertSame(
                $feedModelMock,
                EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_validateFeedConfig')
            );
        }
    }
    /**
     * Test getting the event type from the feed config.
     */
    public function testGetEventType()
    {
        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        // when event_type included in the feed config, should return it
        $feed->setFeedConfig(array('event_type' => 'SomeEventType'));
        $this->assertSame('SomeEventType', $feed->getEventType());
        // when evnet_type not included, should return empty string
        $feed->setFeedConfig(array('local_directory' => 'some/local'));
        $this->assertSame('', $feed->getEventType());
    }
    /**
     * Test _setCheckAndCreateDir method
     */
    public function testSetCheckAndCreateDir()
    {
        $dir = 'EbayEnterprise/Feed/ItemMaster/';
        $fileMock = $this->getMock('Varien_Io_File', array('checkAndCreateFolder'));
        $fileMock->expects($this->once())
            ->method('checkAndCreateFolder')
            ->with($this->equalTo('EbayEnterprise/Feed/ItemMaster/'), $this->equalTo(0750))
            ->will($this->returnValue(true));

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getFsTool'))
            ->getMock();
        $feedModelMock->expects($this->once())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));

        $this->assertSame(
            $feedModelMock,
            EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_setCheckAndCreateDir', array($dir, 'local_directory'))
        );
        $this->assertSame($dir, $feedModelMock->getLocalDIrectory());
    }
    /**
     * When creating the a directory fails, throw an exception indicating the
     * directory could not be created.
     */
    public function testSetCheckAndCreateDirFailure()
    {
        $dir = 'EbayEnterprise/Feed/ItemMaster/';
        $fileMock = $this->getMock('Varien_Io_File', array('checkAndCreateFolder'));
        $fileMock->expects($this->once())
            ->method('checkAndCreateFolder')
            ->with($this->equalTo('EbayEnterprise/Feed/ItemMaster/'), $this->equalTo(0750))
            ->will($this->throwException(new Exception("Unable to create directory '{$dir}'. Access forbidden.")));

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getFsTool'))
            ->getMock();
        $feedModelMock->expects($this->once())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));

        $this->setExpectedException('EbayEnterprise_Catalog_Exception_Feed_File', "Unable to create directory '{$dir}'. Access forbidden.");
        EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_setCheckAndCreateDir', array($dir));
    }
    /**
     * Test joining parts of a path and getting a normalized, joined path.
     */
    public function testNormalPaths()
    {
        // joining these two paths with a `DS` would result in duplicate
        // slashes -> head///tail
        $head = 'head/';
        $tail = '/tail';
        $fsTool = $this->getMock('Varien_Io_File', array('__destruct', 'getCleanPath'));
        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();
        $feed->setFsTool($fsTool);

        // method will join all parts of the path with a `DS`, resulting in
        // initial duplicate slashes which this method will clean away
        $fsTool->expects($this->once())
            ->method('getCleanPath')
            ->with($this->identicalTo($head . DS . $tail))
            ->will($this->returnValue('head/tail'));

        $this->assertSame(
            'head/tail',
            EcomDev_Utils_Reflection::invokeRestrictedMethod($feed, '_normalPaths', array($head, $tail))
        );
    }
    /**
     * Test setUpDirs method when a local directory and sent directory are present
     */
    public function testSetUpDirs()
    {
        $feedConfig = array('local_directory' => 'local/directory', 'sent_directory' => 'sent/directory');
        $cleanLocal = 'Mage/var/local/directory';
        $cleanSent = 'Mage/var/sent/directory';

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_setCheckAndCreateDir', '_normalPaths'))
            ->getMock();
        // inject the dir config post construct so setUpDir can be triggered manually
        $feed->setFeedConfig($feedConfig);
        $feed->expects($this->exactly(2))
            ->method('_normalPaths')
            ->will($this->returnValueMap(array(
                array(Mage::getBaseDir('var'), $feedConfig['local_directory'], $cleanLocal),
                array(Mage::getBaseDir('var'), $feedConfig['sent_directory'], $cleanSent),
            )));
        $feed->expects($this->exactly(2))
            ->method('_setCheckAndCreateDir')
            ->will($this->returnValueMap(array(
                array($cleanLocal, $feed),
                array($cleanSent, $feed),
            )));
        EcomDev_Utils_Reflection::invokeRestrictedMethod($feed, '_setUpDirs');
    }
    /**
     * Test setUpDirs method when a local directory but no sent directory configured
     */
    public function testSetUpDirsLocalOnly()
    {
        $feedConfig = array('local_directory' => 'local/directory',);
        $cleanLocal = 'Mage/var/local/directory';

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_setCheckAndCreateDir', '_normalPaths'))
            ->getMock();
        // inject the dir config post construct so setUpDir can be triggered manually
        $feed->setFeedConfig($feedConfig);
        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with(
                $this->identicalTo(Mage::getBaseDir('var')),
                $this->identicalTo($feedConfig['local_directory'])
            )
            ->will($this->returnValue($cleanLocal));
        $feed->expects($this->once())
            ->method('_setCheckAndCreateDir')
            ->with($this->identicalTo($cleanLocal))
            ->will($this->returnSelf());
        EcomDev_Utils_Reflection::invokeRestrictedMethod($feed, '_setUpDirs');
    }
    /**
     * Test lsLocalDirectory method when local dir already set up
     */
    public function testLsLocalDir()
    {
        $locPath = 'path/to/local/';
        $matches = array('globbed.xml', 'globbed.txt');
        $feedConfig = array('file_pattern' => 'glob*');

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getLocalDirectory', '_normalPaths', '_glob'))
            ->getMock();
        $feed->setFeedConfig($feedConfig);

        $feed->expects($this->once())
            ->method('getLocalDirectory')
            ->will($this->returnValue($locPath));
        // the normalPaths method may append trailing slash if no file extension in pattern
        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with($this->identicalTo($locPath), $this->identicalTo($feedConfig['file_pattern']))
            ->will($this->returnValue($locPath . $feedConfig['file_pattern'] . DS));
        $feed->expects($this->once())
            ->method('_glob')
            ->with($this->identicalTo($locPath . $feedConfig['file_pattern']))
            ->will($this->returnValue($matches));

        $this->assertSame(
            $matches,
            $feed->lsLocalDirectory()
        );
    }
    /**
     * Test lsLocalDirectory method when with default pattern
     */
    public function testLsLocalDirDefaultPattern()
    {
        $locPath = 'path/to/local/';
        $matches = array('globbed.xml', 'globbed.txt');

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getLocalDirectory', '_normalPaths', '_glob'))
            ->getMock();
        $feed->expects($this->once())
            ->method('getLocalDirectory')
            ->will($this->returnValue($locPath));
        // the normalPaths method may append trailing slash if no file extension in pattern
        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with($this->identicalTo($locPath), '*')
            ->will($this->returnValue($locPath . '*' . DS));
        $feed->expects($this->once())
            ->method('_glob')
            ->with($this->identicalTo($locPath . '*'))
            ->will($this->returnValue($matches));

        $this->assertSame(
            $matches,
            $feed->lsLocalDirectory()
        );
    }
    /**
     * Test _mv method
     */
    public function testMvToDir()
    {
        $srcFile = 'EbayEnterprise/Feed/ItemMaster/inbound/Sample1.xml';
        $targetFile = 'EbayEnterprise/Feed/ItemMaster/archive/Sample1.xml';
        $fileMock = $this->getMock('Varien_Io_File', array('mv'));
        $fileMock->expects($this->once())
            ->method('mv')
            ->with(
                $this->equalTo($srcFile),
                $this->equalTo($targetFile)
            )
            ->will($this->returnValue(true));

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getFsTool'))
            ->getMock();
        $feedModelMock->expects($this->once())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));

        $this->assertSame(
            $feedModelMock,
            EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_mv', array($srcFile, $targetFile))
        );
    }
    /**
     * If moving a file fails, an exception should be thrown
     */
    public function testMvFail()
    {
        $srcFile = 'EbayEnterprise/Feed/ItemMaster/inbound/Sample1.xml';
        $targetFile = 'EbayEnterprise/Feed/ItemMaster/archive/Sample1.xml';
        $fileMock = $this->getMock('Varien_Io_File', array('mv'));
        $fileMock->expects($this->once())
            ->method('mv')
            ->with(
                $this->equalTo($srcFile),
                $this->equalTo($targetFile)
            )
            ->will($this->returnValue(false));

        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('getFsTool'))
            ->getMock();
        $feedModelMock->expects($this->once())
            ->method('getFsTool')
            ->will($this->returnValue($fileMock));

        $this->setExpectedException('EbayEnterprise_Catalog_Exception_Feed_File', "Could not move {$srcFile} to {$targetFile}");
        EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_mv', array($srcFile, $targetFile));
    }
    /**
     * Should call _mv with the local directory as the target directory.
     */
    public function testMvToLocalDir()
    {
        $srcFile = 'source/file.xml';
        $locDir = 'local/directory/path';
        $targetFile = 'local/directory/path/file.xml';

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_mv', '_normalPaths'))
            ->getMock();
        // set the local_directory
        $feed->setLocalDirectory($locDir);
        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with($this->identicalTo($locDir), $this->identicalTo('file.xml'))
            ->will($this->returnValue($targetFile));
        $feed->expects($this->once())
            ->method('_mv')
            ->with($this->identicalTo($srcFile), $this->identicalTo($targetFile))
            ->will($this->returnSelf());
        $this->assertSame($targetFile, $feed->mvToLocalDirectory($srcFile));
    }
    /**
     * Test moving the file to the configured sent directory. When the directory
     * has already been set up, simply move the file to the configured directory
     * via_mv
     */
    public function testMvToSentDir()
    {
        $srcFile = 'source/file.xml';
        $sentDir = 'sent/directory';
        $targetFile = 'sent/directory/file.xml';

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_mv', '_normalPaths'))
            ->getMock();
        // set the sent_directory
        $feed->setSentDirectory($sentDir);
        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with($this->identicalTo($sentDir), $this->identicalTo('file.xml'))
            ->will($this->returnValue($targetFile));
        $feed->expects($this->once())
            ->method('_mv')
            ->with($this->identicalTo($srcFile), $this->identicalTo($targetFile))
            ->will($this->returnSelf());
        $this->assertSame($targetFile, $feed->mvToSentDirectory($srcFile));
    }
    /**
     * When the sent directory hasn't been set up yet, check that one was supplied
     * in the configuration. If no, throw an exception.
     */
    public function testMvToSentNoSentConfigured()
    {
        $srcFile = 'source/file.xml';
        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_mv', '_normalPaths', 'setUpDirs', 'getSentDirectory'))
            ->getMock();
        // set after construction to prevent unwanted call to setUpDirs
        $feed->setFeedConfig(array('local_directory' => 'local/only'));

        $feed->expects($this->once())
            ->method('getSentDirectory')
            ->will($this->returnValue(null));
        $feed->expects($this->never())
            ->method('setUpDirs')
            ->will($this->returnSelf());
        $feed->expects($this->never())
            ->method('_normalPaths');
        $feed->expects($this->never())
            ->method('_mv');
        $this->setExpectedException('EbayEnterprise_Catalog_Exception_Feed_File', 'No sent directory configured');
        $this->assertSame($feed, $feed->mvToSentDirectory($srcFile));
    }
    public function provideMvToGlobalConfigDir()
    {
        return array(
            array('mvToProcessingDirectory', 'feedProcessingDirectory'),
            array('mvToExportArchive', 'feedExportArchive'),
            array('mvToImportArchive', 'feedImportArchive'),
        );
    }
    /**
     * Test moving the source file to one of the globally configured directories
     * via the given method - method expected to get path from config registry
     * key matching given config key.
     * @param string $method method to call
     * @param string $feedConfigKey config registry key to get the dir path from
     * @dataProvider provideMvToGlobalConfigDir
     */
    public function testMvToGlobalConfiguredDir($method, $feedConfigKey)
    {
        $srcFile = 'source/file.xml';
        $globalDir = 'global/dir';
        $absPathToDir = "/Mage/var/$globalDir";
        $targetFile = "$absPathToDir/file.xml";

        $feed = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array('_normalPaths', '_mv', '_setCheckAndCreateDir'))
            ->getMock();
        // mock out config value
        $cfg = $this->buildCoreConfigRegistry(array($feedConfigKey => $globalDir));

        $helperMock = $this->getHelperMock('eb2ccore/data', array('getConfigModel'));
        $helperMock->expects($this->any())
            ->method('getConfigModel')
            ->will($this->returnValue($cfg));
        $this->replaceByMock('helper', 'eb2ccore', $helperMock);

        $feed->expects($this->once())
            ->method('_normalPaths')
            ->with(
                $this->identicalTo(Mage::getBaseDir('var')),
                $this->identicalTo($globalDir),
                $this->identicalTo('file.xml')
            )
            ->will($this->returnValue($targetFile));
        $feed->expects($this->once())
            ->method('_setCheckAndCreateDir')
            ->with($this->identicalTo($absPathToDir))
            ->will($this->returnSelf());
        $feed->expects($this->once())
            ->method('_mv')
            ->with($this->identicalTo($srcFile), $this->identicalTo($targetFile))
            ->will($this->returnSelf());

        $this->assertSame($targetFile, $feed->$method($srcFile));
    }
    /**
     * Test make the base acknowledgement file name from config values
     */
    public function testGetBaseAckFileName()
    {
        $this->replaceCoreConfigRegistry(
            array(
                'clientId'               => 'utClientId',
                'storeId'                => 'utStoreId',
                'feedAckTimestampFormat' => '\u\t\D\a\t\e',
                'feedAckFilenameFormat' => 'utAckTest_{eventtype}_{clientid}_{storeid}_{timestamp}.xml',
            )
        );
        $shouldBeFileName = 'utAckTest_utEventType_utClientId_utStoreId_utDate.xml';
        $feedModelMock = $this->getModelMockBuilder('ebayenterprise_catalog/feed_core')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $this->assertSame(
            $shouldBeFileName,
            EcomDev_Utils_Reflection::invokeRestrictedMethod($feedModelMock, '_getBaseAckFileName', array('utEventType'))
        );
    }
}
