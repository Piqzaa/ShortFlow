<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\AiShot;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\EpisodeCovers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EpisodeCoversTest extends WebTestCase
{
    public function testCoversPreferTheChosenVersionAndNeverExposeAnotherOwnersVideos(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', $em->getConnection()->fetchOne('SELECT current_database()'));
        $owner = new User('cover-'.bin2hex(random_bytes(5)).'@example.test');
        $other = new User('cover-other-'.bin2hex(random_bytes(5)).'@example.test');
        foreach ([$owner, $other] as $user) {
            $user->verify();
            $user->setPassword('unused');
            $em->persist($user);
        }
        $recipe = ['model' => 'pika-22', 'seconds' => 5, 'totalSeconds' => 10, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop'];
        $series = new AiSeries($owner, 'Une fouine joue', 'Une blague sans danger.', 'Réaliste', '', $recipe);
        $episode = new AiEpisode($series, 1);
        $episode->beginStory('independent', false);
        $episode->flow('running');
        $em->persist($series);
        $em->persist($episode);
        $em->flush();
        $covers = static::getContainer()->get(EpisodeCovers::class);
        self::assertSame([], $covers->series($owner, [$series]));
        $videos = [];
        foreach ([1, 2] as $position) {
            $video = new VideoAsset($owner, 'Plan '.$position, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 720, 1280, 'h264');
            $job = new AiGeneration($owner, 'Plan '.$position, 'A harmless prank.', 'pika-22', 5, '9:16', 20);
            $job->complete($video);
            $shot = new AiShot($episode, $position, 5);
            $shot->bind($job);
            foreach ([$video, $job, $shot] as $item) {
                $em->persist($item);
            }
            $videos[] = $video;
        }
        $em->flush();
        self::assertSame($videos[1]->getId(), $covers->episodes($owner, [$episode])[$episode->getId()]);
        self::assertSame($videos[1]->getId(), $covers->series($owner, [$series])[$series->getId()]);
        self::assertSame([], $covers->episodes($other, [$episode]));
        self::assertSame([], $covers->series($other, [$series]));
        $client->loginUser($owner);
        $client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.episode-progress', '2 / 2');
        self::assertSelectorExists('.episode-progress[aria-busy="true"] progress[value="2"][max="2"]');
        self::assertSelectorTextContains('.episode-progress', 'Génération en cours');
        $episode->flow('ready');
        $episode->approve($videos[0]);
        $em->flush();
        self::assertSame($videos[0]->getId(), $covers->episodes($owner, [$episode])[$episode->getId()]);
        $client->request('GET', '/studio/series');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.series-item .creation-cover video[data-preview-src]');
    }
}
