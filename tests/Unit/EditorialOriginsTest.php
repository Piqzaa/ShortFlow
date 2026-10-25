<?php

namespace App\Tests\Unit;

use App\Entity\PublicationDraft;
use App\Entity\PublicationDraftItem;
use App\Entity\StoryProposal;
use App\Entity\User;
use App\Entity\VideoAsset;
use PHPUnit\Framework\TestCase;

final class EditorialOriginsTest extends TestCase
{
    public function testExistingStoryJobsKeepTheirTypeAndEditorialJobsUseAnExplicitType(): void
    {
        $owner = new User('synthetic@example.test');
        self::assertSame('story', (new StoryProposal($owner, 1, 'fingerprint', []))->getKind());
        self::assertSame('editorial', (new StoryProposal($owner, 1, 'fingerprint', [], 'editorial'))->getKind());
    }

    public function testClearingOrChangingGeneratedTextRecordsAHumanEdit(): void
    {
        $owner = new User('synthetic@example.test');
        $video = new VideoAsset($owner, 'Synthetic', 'synthetic.mp4', 'video/mp4', 100, 5, 360, 640, 'h264');
        (new \ReflectionProperty($video, 'id'))->setValue($video, 1);
        $draft = new PublicationDraft($owner, 'Synthetic', new \DateTimeImmutable());
        $draft->edit('Synthetic', ['youtube'], [], new \DateTimeImmutable());
        $item = new PublicationDraftItem($draft, $video, 0);
        $item->setContent(['youtube' => ['title' => 'Proposé', 'description' => 'Texte proposé.']]);
        $draft->addItem($item);
        $draft->setEditorial(['generated' => ['youtube' => ['title' => hash('sha256', 'Proposé'), 'description' => hash('sha256', 'Texte proposé.')]]]);
        $previous = $draft->content();
        $item->setContent(['youtube' => ['title' => 'Mon titre', 'description' => '']]);
        $draft->trackEditorialChanges($previous);
        self::assertTrue($draft->getEditorial()['human']['youtube']['title']);
        self::assertTrue($draft->getEditorial()['human']['youtube']['description']);
        self::assertSame('', $draft->content()['youtube']['videos'][1]['description']);
    }
}
