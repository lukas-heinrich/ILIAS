<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

use PHPUnit\Framework\TestCase;

require_once("./Services/MediaObjects/ImageMap/class.ilMapArea.php");

/**
 * @author Alexander Killing <killing@leifos.de>
 */
class PCInteractiveImageTest extends COPageTestBase
{
    /**
     * @return (ilObjMediaObject&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getMediaObjectMock()
    {
        $media_item = new ilMediaItem();
        $media_item->setWidth("100");
        $media_item->setHeight("50");
        $media_object = $this->getMockBuilder(ilObjMediaObject::class)
                             ->disableOriginalConstructor()
                             ->getMock();
        $media_object->method("getMediaItem")
                  ->willReturnCallback(fn () => $media_item);
        return $media_object;
    }

    /**
     * @return (\ILIAS\Repository\Object\ObjectAdapter&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getObjectAdapterMock()
    {
        $object_adapter = $this->getMockBuilder(\ILIAS\Repository\Object\ObjectAdapter::class)
                             ->disableOriginalConstructor()
                             ->getMock();
        $object_adapter->method("getTypeForObjId")
                     ->willReturnCallback(fn () => "dummy");
        return $object_adapter;
    }

    public function testConstruction(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $this->assertEquals(
            ilPCInteractiveImage::class,
            get_class($pc)
        );
    }

    public function testCreate(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->create($page, "pg");
        $this->assertXmlEquals(
            '<PageObject HierId="pg"></PageObject>',
            $page->getXMLFromDom()
        );
    }

    public function testCreateAlias(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");

        $expected = <<<EOT
<PageObject HierId="pg"><PageContent><InteractiveImage><MediaAlias OriginId="il__mob_0"/><MediaAliasItem Purpose="Standard"><Layout HorizontalAlign="Left"/></MediaAliasItem></InteractiveImage></PageContent></PageObject>
EOT;

        $this->assertXmlEquals(
            $expected,
            $page->getXMLFromDom()
        );
    }

    public function testDump(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");

        $expected = <<<EOT
<PageContent><InteractiveImage><MediaAlias OriginId="il__mob_0"/><MediaAliasItem Purpose="Standard"><Layout HorizontalAlign="Left"/></MediaAliasItem></InteractiveImage></PageContent>
EOT;

        $this->assertXmlEquals(
            $expected,
            $pc->dumpXML()
        );
    }

    public function testAddContentPopup(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");
        $pc->addContentPopup();

        //echo $page->getXMLFromDom();
        //exit;

        $expected = <<<EOT
<PageObject HierId="pg"><PageContent><InteractiveImage><MediaAlias OriginId="il__mob_0"/><MediaAliasItem Purpose="Standard"><Layout HorizontalAlign="Left"/></MediaAliasItem><ContentPopup Title="" Nr="1"/></InteractiveImage></PageContent></PageObject>
EOT;

        $this->assertXmlEquals(
            $expected,
            $page->getXMLFromDom()
        );
    }

    public function testGetPopups(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");
        $pc->addContentPopup();
        $page->addHierIDs();
        $popups = $pc->getPopups();

        $expected = [
            0 => [
                "title" => "",
                "nr" => "1",
                "pc_id" => "",
                "hier_id" => "1_1"
            ]
        ];

        $this->assertEquals(
            $expected,
            $popups
        );
    }

    public function testSavePopups(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");
        $pc->addContentPopup();
        $page->addHierIDs();
        $popups = $pc->savePopups(
            [
                "1_1:" => "Test Title"
            ]
        );

        $expected = [
            0 => [
                "title" => "Test Title",
                "nr" => "1",
                "pc_id" => "",
                "hier_id" => "1_1"
            ]
        ];

        $this->assertEquals(
            $expected,
            $pc->getPopups()
        );
    }

    public function testDeletePopup(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = new ilPCInteractiveImage($page);
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");
        $pc->addContentPopup();
        $pc->addContentPopup();
        $page->addHierIDs();
        $popups = $pc->savePopups(
            [
                "1_1:" => "Test Title 1",
                "1_2:" => "Test Title 2"
            ]
        );
        $pc->deletePopup("1_1", "");
        $page->stripHierIDs();

        $expected = <<<EOT
<PageObject><PageContent><InteractiveImage><MediaAlias OriginId="il__mob_0"/><MediaAliasItem Purpose="Standard"><Layout HorizontalAlign="Left"/></MediaAliasItem><ContentPopup Title="Test Title 2" Nr="2"/></InteractiveImage></PageContent></PageObject>
EOT;

        $this->assertXmlEquals(
            $expected,
            $page->getXMLFromDom()
        );
    }

    protected function getInteractiveImageInPage(ilPageObject $page): ilPCInteractiveImage
    {
        $pc = new ilPCInteractiveImage(
            $page,
            null,
            $this->getObjectAdapterMock()
        );
        $pc->setMediaObject($this->getMediaObjectMock());
        $pc->createAlias($page, "pg");
        $page->addHierIDs();
        $page->insertPCIds();
        $pc->setHierId("1");
        $pc->setPCId($page->getPCIdForHierId("1"));
        $pc->setNode($pc->getNode());
        return $pc;
    }

    public function testAddTriggerArea(): void
    {
        $page = $this->getEmptyPageWithDom();
        $pc = $this->getInteractiveImageInPage($page);

        $pc->addTriggerArea(
            $pc->getStandardAliasItem(),
            IL_AREA_RECT,
            "20,20,200,200",
            "Area Title"
        );
        $page->stripHierIDs();
        $page->stripPCIDs();

        $expected = <<<EOT
<PageObject><PageContent><InteractiveImage><MediaAlias OriginId="il__mob_0"/><MediaAliasItem Purpose="Standard"><Layout HorizontalAlign="Left"/><MapArea Shape="Rect" Coords="20,20,200,200" Id="1"><ExtLink Href="#">Area Title</ExtLink></MapArea></MediaAliasItem><Trigger Type="Area" Title="Area Title" Nr="1" OverlayX="0" OverlayY="0" PopupX="0" PopupY="0" PopupWidth="150" PopupHeight="200"/></InteractiveImage></PageContent></PageObject>
EOT;

        $this->assertXmlEquals(
            $expected,
            $page->getXMLFromDom()
        );
    }
}
