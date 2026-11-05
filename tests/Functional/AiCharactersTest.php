<?php

namespace App\Tests\Functional;

use App\Entity\AiCharacter;
use App\Entity\User;
use App\Service\AiCharacters;
use App\Service\AiReferenceMaintenance;
use App\Service\AiReferenceStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiCharactersTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $owner;
    private int $other;
    private array $temps = [];
    private array $rows = [];
    private array $counts;
    private int $http = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $this->counts = $this->counts();
        $db->beginTransaction();
        foreach (['youtube', 'instagram', 'tiktok', 'ai', 'story'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(function (): never {
                ++$this->http;
                throw new \LogicException('No HTTP allowed.');
            }));
        }
        foreach (['owner', 'other'] as $key) {
            $user = new User('character-'.$key.'-'.bin2hex(random_bytes(5)).'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->em->flush();
            $this->$key = $user->getId();
        }
        $this->login($this->owner);
        if (!is_dir(dirname(__DIR__, 2).'/artifacts/characters')) {
            mkdir(dirname(__DIR__, 2).'/artifacts/characters', 0775, true);
        }
    }

    private function counts(): array
    {
        $db = $this->em->getConnection();
        $rows = [];
        foreach ($db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
            $rows[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$db->quoteIdentifier($table));
        }

        return $rows;
    }

    protected function tearDown(): void
    {
        try {
            $this->em->getConnection()->rollBack();
            $this->em->clear();
            self::assertSame($this->counts, $this->counts());
            self::assertSame(0, $this->http);
        } finally {
            $root = dirname(__DIR__, 2).'/var/storage/ai-references/test';
            foreach ([$this->owner, $this->other] as $id) {
                foreach (glob($root.'/'.$id.'/*.jpg') ?: [] as $path) {
                    if (is_file($path) && !is_link($path)) {
                        unlink($path);
                    }
                }if (is_dir($root.'/'.$id)) {
                    rmdir($root.'/'.$id);
                }
            }
            foreach ($this->temps as $path) {
                if (is_file($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    rmdir($path);
                }
            }
            file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/'.$this->name().'.json', json_encode(['before' => $this->counts, 'after' => $this->counts(), 'http' => $this->http, 'screens' => $this->rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            parent::tearDown();
        }
    }

    private function login(int $id): void
    {
        $this->client->loginUser($this->em->find(User::class, $id));
    }

    private function image(bool $valid = true): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'character-');
        $this->temps[] = $path;
        if ($valid) {
            (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640', '-frames:v', '1', '-c:v', 'mjpeg', '-f', 'image2', $path]))->mustRun();
        } else {
            file_put_contents($path, 'not an image');
        }

        return new UploadedFile($path, 'portrait.jpg', 'image/jpeg', null, true);
    }

    private function snap(string $name): void
    {
        $html = $this->client->getResponse()->getContent();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/'.$name.'.html', $html);
        $this->rows[$name] = hash('sha256', $html);
    }

    private function create(): AiCharacter
    {
        $crawler = $this->client->request('GET', '/studio/personnages/nouveau');
        $form = $crawler->selectButton('Enregistrer le personnage')->form(['ai_character[name]' => 'Milo', 'ai_character[description]' => 'Chat roux en veste bleue.']);
        $this->client->request('POST', '/studio/personnages/nouveau', $form->getPhpValues(), ['ai_character' => ['portrait' => $this->image()]]);
        self::assertResponseStatusCodeSame(303);

        return $this->em->getRepository(AiCharacter::class)->findOneBy(['owner' => $this->owner]);
    }

    private function cast(): array
    {
        $first = $this->create();
        $owner = $this->em->find(User::class, $this->owner);
        $second = static::getContainer()->get(AiCharacters::class)->save($owner, null, 0, 'Lea', 'White cat with green coat.', $this->image(), []);

        return [$first, $second];
    }

    public function testQuickCreateSavesAPrivateFicheAndMakesItAvailableToRefresh(): void
    {
        $crawler = $this->client->request('GET', '/studio/personnages/nouveau?rapide=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.character-form details summary', 'Description et vues supplémentaires');
        $form = $crawler->selectButton('Enregistrer le personnage')->form();
        $form['ai_character[name]'] = 'Quick Milo';
        $form['ai_character[portrait]']->upload($this->image()->getPathname());
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/studio/personnages/choix');
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $data['choices']);
        self::assertStringStartsWith('Quick Milo', $data['choices'][0]['label']);
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiGeneration::class)->count(['owner' => $this->owner]));
        $this->snap('quick-created');
    }

    public function testEmptyLibraryOffersFirstCharacterOnlyInReferenceMode(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('[data-character-pool][hidden]');
        self::assertSelectorTextContains('[data-character-pool] a', 'Créer mon premier personnage');
        $values = $crawler->filter('#ai-idea-form')->form()->getPhpValues();
        $values += ['revision' => $crawler->filter('#ai-idea-form')->attr('data-idea-save-revision-value'), 'editor' => str_repeat('e', 32), 'sequence' => '1'];
        $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'A cat waves.', 'model' => 'kling-o3-subject', 'source' => 'subject', 'seconds' => '5', 'ratio' => '9:16', 'accessMode' => 'api']);
        $this->client->request('POST', '/studio/creation/idee', $values);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('[data-character-pool]:not([hidden])');
        self::assertSelectorTextContains('[data-character-empty]', 'Aucun personnage enregistré');
        self::assertSelectorCount(0, '[data-character-pool] input[type="checkbox"]');
        $this->snap('empty-reference-library');
    }

    public function testNewReferenceChoicesFitDurableGenerationStorage(): void
    {
        $catalog = static::getContainer()->get(\App\Service\AiCatalog::class);
        $owner = $this->em->find(User::class, $this->owner);
        $ids = [];
        foreach (\App\Service\CharacterVideoModels::MODELS as $id => $model) {
            $job = new \App\Entity\AiGeneration($owner, 'Synthetic storage contract', 'A cat waves.', $id, $model['seconds'][0], $model['ratios'][0], $catalog->estimate($id, $model['seconds'][0], 1));
            $this->em->persist($job);
            $ids[] = $id;
        }
        $this->em->flush();
        self::assertSame($ids, $this->em->getConnection()->fetchFirstColumn('SELECT model FROM ai_generation WHERE owner_id = ? ORDER BY id', [$this->owner]));
    }

    public function testRefreshListsOnlyOwnedActiveCharactersAndNeverExposesStorage(): void
    {
        [$a, $b] = $this->cast();
        $service = static::getContainer()->get(AiCharacters::class);
        $foreign = $service->save($this->em->find(User::class, $this->other), null, 0, 'Private foreign cat', '', $this->image(), []);
        $this->client->request('GET', '/studio/personnages/choix');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(2, $data['choices']);
        self::assertSame([$b->getId().':1', $a->getId().':1'], array_column($data['choices'], 'value'));
        self::assertStringNotContainsString($foreign->getPortrait(), $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Private foreign cat', $this->client->getResponse()->getContent());
        $service->archive($this->em->find(User::class, $this->owner), $b->getId(), 1, true);
        $this->client->request('GET', '/studio/personnages/choix');
        self::assertCount(1, json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['choices']);
        $this->snap('choices-refreshed');
    }

    public function testExplicitEmptyCheckboxSelectionClearsAutosaveAndScenePresence(): void
    {
        [$a, $b] = $this->cast();
        $crawler = $this->client->request('GET', '/studio/creation');
        $values = $crawler->filter('#ai-idea-form')->form()->getPhpValues();
        $values += ['revision' => $crawler->filter('#ai-idea-form')->attr('data-idea-save-revision-value'), 'editor' => str_repeat('c', 32), 'sequence' => '1'];
        $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'Two cats wave.', 'model' => 'kling-o3-subject', 'source' => 'subject', 'seconds' => '5', 'ratio' => '9:16', 'accessMode' => 'api', 'characterSelectionManaged' => '1', 'characterSelection' => [$a->getId().':1', $b->getId().':1'], 'shots' => [['prompt' => 'The cats wave.', 'seconds' => '5', 'promptOnly' => 'manual', 'characterIdsManaged' => '1', 'characterIds' => [(string) $a->getId(), (string) $b->getId()]]]]);
        $this->client->request('POST', '/studio/creation/idee', $values);
        self::assertResponseIsSuccessful();
        $page = $this->client->request('GET', '/studio/creation');
        self::assertSame([$a->getId().':1', $b->getId().':1'], $page->filter('#ai-idea-form')->form()->getPhpValues()['ai_idea']['characterSelection']);
        $values['sequence'] = '2';
        $values['ai_idea']['characterSelection'] = [];
        $values['ai_idea']['shots'][0]['characterIds'] = [];
        $this->client->request('POST', '/studio/creation/idee', $values);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorCount(0, 'input[name="ai_idea[characterSelection][]"]:checked');
        self::assertSelectorCount(0, 'input[name="ai_idea[shots][0][characterIds][]"]:checked');
        self::assertSelectorExists('a[href="/studio/personnages/nouveau?rapide=1"]');
        $this->snap('checkboxes-cleared');
    }

    public function testAutosaveKeepsFrozenSelectionAndOlderPayloadCannotEraseSceneCharacters(): void
    {
        [$a, $b] = $this->cast();
        $crawler = $this->client->request('GET', '/studio/creation');
        $revision = $crawler->filter('#ai-idea-form')->attr('data-idea-save-revision-value');
        $values = $crawler->filter('#ai-idea-form')->form()->getPhpValues();
        $values += ['revision' => $revision, 'editor' => str_repeat('a', 32), 'sequence' => '1'];
        $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'A cat explores a garden.', 'model' => 'vidu-q1-subject', 'source' => 'subject', 'ratio' => '9:16', 'seconds' => '5', 'accessMode' => 'api', 'characterSelectionManaged' => '1', 'characterSelection' => [$a->getId().':1'], 'shots' => [['prompt' => 'A cat walks slowly.', 'seconds' => '5', 'promptOnly' => 'manual', 'characterIdsManaged' => '1', 'characterIds' => [(string) $a->getId()]]]]);
        $this->client->request('POST', '/studio/creation/idee', $values);
        self::assertResponseIsSuccessful();
        $owner = $this->em->find(User::class, $this->owner);
        static::getContainer()->get(AiCharacters::class)->save($owner, $a->getId(), 1, 'Updated Milo', 'A different coat.', $this->image(), []);
        unset($values['ai_idea']['characterSelection'], $values['ai_idea']['characterSelectionManaged'], $values['ai_idea']['shots'][0]['characterIds'], $values['ai_idea']['shots'][0]['characterIdsManaged']);
        $values['sequence'] = '2';
        $this->client->request('POST', '/studio/creation/idee', $values);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('input[name="ai_idea[characterSelection][]"][value="'.$a->getId().':1"][checked]');
        self::assertSelectorExists('input[name="ai_idea[shots][0][characterIds][]"][value="'.$a->getId().'"][checked]');
        $this->snap('selection-autosave');
    }

    public function testSelectionRejectsForeignStaleAndDuplicateVersionsButKeepsFrozenArchive(): void
    {
        [$a, $b] = $this->cast();
        $owner = $this->em->find(User::class, $this->owner);
        $library = static::getContainer()->get(\App\Service\AiCharacterSelection::class);
        $service = static::getContainer()->get(AiCharacters::class);
        $other = $this->em->find(User::class, $this->other);
        $foreign = $service->save($other, null, 0, 'Foreign', '', $this->image(), []);
        self::assertNotContains($foreign->getId().':1', $library->choices($owner));
        $data = ['characterSelectionManaged' => '1', 'characterSelection' => [$a->getId().':1', $b->getId().':1']];
        $pool = $library->resolve($owner, $data);
        self::assertCount(2, $pool);
        $aId = $a->getId();
        $old = $a->getPortrait();
        $service->save($owner, $aId, 1, 'New Milo', 'New appearance', $this->image(), []);
        $service->archive($owner, $aId, 2, true);
        self::assertSame($pool, $library->resolve($owner, $data, ['characterPool' => $pool]));
        self::assertSame($old, $pool[0]['portrait']);
        self::assertSame([], $library->resolve($owner, ['characterSelectionManaged' => '1', 'characterSelection' => []], ['characterPool' => $pool]));
        self::assertNull($library->resolve($owner, []));
        self::assertSame($pool, $library->resolve($owner, [], ['characterPool' => $pool]));
        foreach ([[[$foreign->getId().':1'], 'indisponible'], [[$aId.':1'], 'changé'], [[$b->getId().':1', $b->getId().':1'], 'une seule version']] as [$tokens, $expectedError]) {
            try {
                $library->resolve($owner, ['characterSelectionManaged' => '1', 'characterSelection' => $tokens]);
                self::fail('Invalid selection accepted.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString($expectedError, $error->getMessage());
            }
        }
    }

    public function testStudioThreeScenesUseOnlyTheirFrozenCharactersAndQuoteChangesWithPresence(): void
    {
        [$a, $b] = $this->cast();
        $crawler = $this->client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        $this->snap('selection-studio');
        $form = $crawler->selectButton('Voir le récapitulatif')->form();
        $values = $form->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'Two cats explore a garden.', 'model' => 'vidu-q1-subject', 'source' => 'subject', 'seconds' => '5', 'target' => '15', 'planCount' => '3', 'ratio' => '9:16', 'accessMode' => 'api', 'characterSelection' => [$a->getId().':1', $b->getId().':1'], 'characterSelectionManaged' => '1', 'shots' => []]);
        foreach ([[(string) $a->getId()], [(string) $b->getId()], [(string) $a->getId(), (string) $b->getId()]] as $ids) {
            $values['ai_idea']['shots'][] = ['prompt' => 'A cat walks slowly across the garden.', 'seconds' => '5', 'promptOnly' => 'manual', 'characterIdsManaged' => '1', 'characterIds' => $ids];
        }
        $invalid = $values;
        $invalid['ai_idea']['_token'] = 'invalid';
        $this->client->request('POST', '/studio/creation', $invalid);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiEpisode::class)->count(['owner' => $this->owner]));
        $invalid = $values;
        $invalid['ai_idea']['characterSelection'] = ['999999999:1'];
        $this->client->request('POST', '/studio/creation', $invalid);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/studio/creation', $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->snap('selection-review');
        $episode = $this->em->getRepository(\App\Entity\AiEpisode::class)->findOneBy(['owner' => $this->owner]);
        $flow = static::getContainer()->get(\App\Service\AiEpisodeFlow::class);
        $quote = $flow->quote($episode);
        $refs = $quote['references'];
        self::assertSame($a->getPortrait(), $refs[0]['start']);
        self::assertSame([], $refs[0]['subjects']);
        self::assertSame($b->getPortrait(), $refs[1]['start']);
        self::assertSame([$b->getPortrait()], $refs[2]['subjects']);
        self::assertSame(['Milo'], array_column($refs[0]['subjectDetails'], 'name'));
        self::assertStringNotContainsString('Lea', $refs[0]['prompt']);
        self::assertStringContainsString('White cat with green coat.', $refs[1]['prompt']);
        self::assertSame(['Milo', 'Lea'], array_column($refs[2]['subjectDetails'], 'name'));
        $owner = $this->em->find(User::class, $this->owner);
        static::getContainer()->get(AiCharacters::class)->save($owner, $a->getId(), 1, 'Milo changed', '', $this->image(), []);
        self::assertSame($quote['hash'], $flow->quote($episode)['hash']);
        // Old HTML does not know the selection fields: omission must preserve the scene.
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $this->snap('selection-edit');
        $old = $crawler->filter('form#ai-idea-form')->form()->getPhpValues();
        $old['intent'] = 'compose';
        unset($old['ai_idea']['characterSelection'], $old['ai_idea']['characterSelectionManaged']);
        foreach ($old['ai_idea']['shots'] as &$row) {
            unset($row['characterIds'], $row['characterIdsManaged']);
        }
        unset($row);
        $this->client->request('POST', '/studio/series/episodes/'.$episode->getId(), $old);
        self::assertResponseStatusCodeSame(303);
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $episode->getId());
        self::assertSame([(string) $a->getId()], $flow->shots($episode)[0]->getInputs()['characterIds']);
        // No-JS redistribution must not discard a scene carrying only a selected character.
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $replan = $crawler->filter('form#ai-idea-form')->form()->getPhpValues();
        $replan['intent'] = 'replan';
        $replan['ai_idea']['target'] = '10';
        $replan['ai_idea']['planCount'] = '2';
        $replan['ai_idea']['shots'][2]['prompt'] = '';
        $this->client->request('POST', '/studio/series/episodes/'.$episode->getId(), $replan);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-errors, .form-error-message', 'plan déjà renseigné');
        self::assertSelectorCount(3, '[data-shot-editor-target="list"] > .shot-card');
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $episode->getId());
        $shots = $flow->shots($episode);
        $rows = array_map(static fn ($s): array => ['prompt' => $s->getPrompt(), 'seconds' => $s->getSeconds()] + $s->getInputs(), $shots);
        $rows[0]['characterIds'] = [(string) $b->getId()];
        $flow->save($episode, $episode->getRevision(), $episode->getTitle(), $rows);
        self::assertNotSame($quote['hash'], $flow->quote($episode)['hash']);
        try {
            $flow->confirm($episode, $quote['hash']);
            self::fail('The old quote must not authorize changed character presence.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        $rows[0]['characterIds'] = [];
        $flow->save($episode, $episode->getRevision(), $episode->getTitle(), $rows);
        try {
            $flow->quote($episode);
            self::fail('Empty Vidu scene must not receive the whole cast.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('référence', $error->getMessage());
        }
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
    }

    public function testSeriesSelectionFreezesExistingEpisodeAndExplicitClearDoesNotRefreshIt(): void
    {
        [$a, $b] = $this->cast();
        $crawler = $this->client->request('GET', '/studio/series/nouvelle');
        self::assertResponseIsSuccessful();
        $this->snap('selection-series');
        $form = $crawler->selectButton('Créer ma série')->form();
        $values = $form->getPhpValues();
        $values['ai_series'] = array_replace($values['ai_series'], ['name' => 'Two cats', 'theme' => 'Garden discoveries', 'model' => 'vidu-q1-subject', 'seconds' => '5', 'totalSeconds' => '15', 'episodeCount' => '1', 'ratio' => '9:16', 'accessMode' => 'api', 'imageFit' => 'crop', 'characterSelectionManaged' => '1', 'characterSelection' => [$a->getId().':1', $b->getId().':1']]);
        $this->client->request('POST', '/studio/series/nouvelle', $values);
        self::assertResponseStatusCodeSame(303);
        $series = $this->em->getRepository(\App\Entity\AiSeries::class)->findOneBy(['owner' => $this->owner]);
        self::assertCount(2, $series->getRecipe()['characterPool']);
        $episode = $this->em->getRepository(\App\Entity\AiEpisode::class)->findOneBy(['series' => $series]);
        $episode->configurePreparation($series->getRecipe() + ['inputVersion' => 4], 'A quiet garden.', 'independent', false);
        $this->em->flush();
        $frozen = $episode->getFlowRecipe();
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId().'/configuration');
        self::assertResponseIsSuccessful();
        $this->snap('selection-series-configured');
        $form = $crawler->selectButton('Enregistrer la configuration')->form();
        $values = $form->getPhpValues();
        $values['ai_series']['characterSelection'] = [];
        $values['ai_series']['model'] = 'kling-25';
        $values['ai_series']['seconds'] = '5';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $series = $this->em->find(\App\Entity\AiSeries::class, $series->getId());
        self::assertSame([], $series->getRecipe()['characterPool']);
        self::assertSame($frozen, $episode->getFlowRecipe());
    }

    public function testSceneResolutionRejectsIncompatibilityMissingFilesAndUnknownCharactersWithoutFallback(): void
    {
        [$a, $b] = $this->cast();
        $owner = $this->em->find(User::class, $this->owner);
        $pool = static::getContainer()->get(\App\Service\AiCharacterSelection::class)->resolve($owner, ['characterSelectionManaged' => '1', 'characterSelection' => [$a->getId().':1', $b->getId().':1']]);
        $inputs = static::getContainer()->get(\App\Service\AiPlanInputs::class);
        $recipe = ['model' => 'kling-3-image', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop', 'inputVersion' => 4, 'characterPool' => $pool];
        $episode = new \App\Entity\AiEpisode($owner, 1, 'Garden', 'A garden.', $recipe);
        $episode->configurePreparation($recipe, 'A garden.', 'independent', false);
        $shot = new \App\Entity\AiShot($episode, 1, 5);
        $shot->configureInputs(['startReference' => $a->getPortrait(), 'characterIds' => []]);
        self::assertSame([], $inputs->resolve($episode, $shot, 0, 1)['subjects']);
        self::assertSame([], $inputs->resolve($episode, $shot, 0, 1)['subjectDetails']);
        $shot->configureInputs(['startReference' => $a->getPortrait(), 'characterIds' => [(string) $a->getId()]]);
        try {
            $inputs->resolve($episode, $shot, 0, 1);
            self::fail('Kling requires grouped views.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('vues', $error->getMessage());
        }
        $shot->configureInputs(['startReference' => $a->getPortrait(), 'characterIds' => ['999999999']]);
        try {
            $inputs->resolve($episode, $shot, 0, 1);
            self::fail('Unknown scene character must be refused.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('ne fait pas partie', $error->getMessage());
        }
        $recipe['model'] = 'kling-25';
        $episode->configurePreparation($recipe, 'A garden.', 'independent', false);
        $shot->configureInputs(['characterIds' => [(string) $a->getId()]]);
        try {
            $inputs->resolve($episode, $shot, 0, 1);
            self::fail('A text model must not ignore selected characters.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('ne reçoit pas', $error->getMessage());
        }
        $recipe['model'] = 'vidu-q1-subject';
        $recipe['characterPool'][0]['views'] = [$b->getPortrait()];
        $episode->configurePreparation($recipe, 'A garden.', 'independent', false);
        $resolved = $inputs->resolve($episode, $shot, 0, 1);
        self::assertSame($a->getPortrait(), $resolved['start']);
        self::assertSame([], $resolved['subjectDetails'][0]['views']);
        self::assertSame([$b->getPortrait()], $episode->getFlowRecipe()['characterPool'][0]['views']);
        unset($recipe['characterPool']);
        $recipe['reference'] = $a->getPortrait();
        $episode->configurePreparation($recipe, 'A garden.', 'independent', false);
        $shot->configureInputs([]);
        self::assertSame($a->getPortrait(), $inputs->resolve($episode, $shot, 0, 1)['start']);
        $recipe['characterPool'] = $pool;
        $recipe['reference'] = null;
        $episode->configurePreparation($recipe, 'A garden.', 'independent', false);
        $shot->configureInputs(['characterIds' => [(string) $a->getId()]]);
        unlink(static::getContainer()->get(AiReferenceStorage::class)->path($owner, $a->getPortrait()));
        try {
            $inputs->resolve($episode, $shot, 0, 1);
            self::fail('Missing frozen image must not be replaced.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Image indisponible', $error->getMessage());
        }
    }

    public function testModelChangeRejectsMissingViewsThenRequiresANewQuoteForExplicitNewVersion(): void
    {
        $character = $this->create();
        $characterId = $character->getId();
        $portrait = $character->getPortrait();
        $crawler = $this->client->request('GET', '/studio/creation');
        $values = $crawler->selectButton('Voir le récapitulatif')->form()->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'A cat explores a garden.', 'model' => 'vidu-q1-subject', 'source' => 'subject', 'seconds' => '5', 'target' => '10', 'planCount' => '2', 'ratio' => '9:16', 'accessMode' => 'api', 'characterSelectionManaged' => '1', 'characterSelection' => [$characterId.':1'], 'shots' => array_fill(0, 2, ['prompt' => 'A cat walks slowly across the garden.', 'seconds' => '5', 'promptOnly' => 'manual', 'characterIdsManaged' => '1', 'characterIds' => [(string) $characterId]])]);
        $this->client->request('POST', '/studio/creation', $values);
        self::assertResponseStatusCodeSame(303);
        $episode = $this->em->getRepository(\App\Entity\AiEpisode::class)->findOneBy(['owner' => $this->owner]);
        $episodeId = $episode->getId();
        $flow = static::getContainer()->get(\App\Service\AiEpisodeFlow::class);
        $oldQuote = $flow->quote($episode);
        $url = '/studio/series/episodes/'.$episodeId;
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Voir le récapitulatif')->form()->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea']['model'] = 'kling-3-image';
        $values['ai_idea']['source'] = 'image';
        foreach ($values['ai_idea']['shots'] as &$row) {
            $row['startReference'] = $portrait;
        }
        unset($row);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form#ai-idea-form');
        self::assertSelectorNotExists('#story-quote');
        $this->snap('r73c-model-refused');
        $this->em->clear();
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $episodeId);
        self::assertSame('kling-3-image', $episode->getFlowRecipe()['model']);
        self::assertSame($portrait, $episode->getFlowRecipe()['characterPool'][0]['portrait']);
        try {
            $flow->quote($episode);
            self::fail('The saved draft must not receive a quote without Kling views.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('vues', $error->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiGeneration::class)->count(['owner' => $this->owner]));
        $owner = $this->em->find(User::class, $this->owner);
        $changed = static::getContainer()->get(AiCharacters::class)->save($owner, $characterId, 1, 'Milo', 'Orange cat wearing a blue jacket.', null, [$this->image()]);
        $views = $changed->getViews();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Voir le récapitulatif')->form()->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea']['model'] = 'kling-3-image';
        $values['ai_idea']['source'] = 'image';
        $values['ai_idea']['characterSelection'] = [$characterId.':2'];
        foreach ($values['ai_idea']['shots'] as &$row) {
            $row['startReference'] = $portrait;
        }
        unset($row);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->snap('r73c-model-new-quote');
        $this->em->clear();
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $episodeId);
        $newQuote = $flow->quote($episode);
        self::assertSame('kling-3-image', $episode->getFlowRecipe()['model']);
        self::assertSame(2, $episode->getFlowRecipe()['characterPool'][0]['revision']);
        self::assertNotSame($oldQuote['hash'], $newQuote['hash']);
        foreach ($newQuote['references'] as $reference) {
            self::assertSame($portrait, $reference['start']);
            self::assertSame(['Milo'], array_column($reference['subjectDetails'], 'name'));
            self::assertSame($views, $reference['subjectDetails'][0]['views']);
        }
        try {
            $flow->confirm($episode, $oldQuote['hash']);
            self::fail('Changing the model must not reuse the old quote consent.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        self::assertNull($episode->getConsentHash());
        self::assertSame('draft', $episode->getFlowState());
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiGeneration::class)->count(['owner' => $this->owner]));
        $media = [];
        foreach ([$portrait, ...$views] as $file) {
            $media[$file] = 'r73c-'.$file;
            copy(static::getContainer()->get(AiReferenceStorage::class)->path($this->em->find(User::class, $this->owner), $file), dirname(__DIR__, 2).'/artifacts/characters/'.$media[$file]);
        }
        file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/r73c-model-media.json', json_encode($media, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/r73c-model-quotes.json', json_encode(['old' => $oldQuote, 'new' => $newQuote], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function testPersistedLegacyFramesStayDistinctFromMissingOrExplicitEmptyCharacterPresence(): void
    {
        [$a, $b] = $this->cast();
        $owner = $this->em->find(User::class, $this->owner);
        $recipe = ['model' => 'kling-25-image', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => $a->getPortrait(), 'endReference' => null, 'imageFit' => 'crop'];
        $episode = new \App\Entity\AiEpisode($owner, 1, 'Legacy garden', 'A cat walks in a garden.', $recipe);
        $episode->configurePreparation($recipe, 'A cat walks in a garden.', 'independent', false);
        $this->em->persist($episode);
        $shot = new \App\Entity\AiShot($episode, 1, 5);
        $shot->revise('A cat walks slowly across the garden.', 5);
        $this->em->persist($shot);
        $this->em->flush();
        $id = $episode->getId();
        $shotId = $shot->getId();
        $this->em->clear();
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $id);
        $shot = $this->em->find(\App\Entity\AiShot::class, $shotId);
        $inputs = static::getContainer()->get(\App\Service\AiPlanInputs::class);
        $resolved = $inputs->resolve($episode, $shot, 0, 1);
        self::assertArrayNotHasKey('characterPool', $episode->getFlowRecipe());
        self::assertArrayNotHasKey('characterIds', $shot->getInputs());
        self::assertSame($a->getPortrait(), $resolved['start']);
        self::assertSame([], $resolved['subjectDetails']);
        self::assertSame([], $resolved['subjects']);
        $recipe['inputVersion'] = 4;
        $recipe['characterPool'] = [];
        $episode->configurePreparation($recipe, $episode->getPrompt(), 'independent', false);
        $shot->configureInputs(['startReference' => $a->getPortrait()]);
        $this->em->flush();
        $this->em->clear();
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $id);
        $shot = $this->em->find(\App\Entity\AiShot::class, $shotId);
        try {
            $inputs->resolve($episode, $shot, 0, 1);
            self::fail('Missing scene presence must not be inferred from an explicit pool.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Choisis les personnages', $error->getMessage());
        }
        $shot->configureInputs(['startReference' => $a->getPortrait(), 'characterIds' => []]);
        $this->em->flush();
        $this->em->clear();
        $episode = $this->em->find(\App\Entity\AiEpisode::class, $id);
        $shot = $this->em->find(\App\Entity\AiShot::class, $shotId);
        self::assertSame([], $episode->getFlowRecipe()['characterPool']);
        self::assertSame([], $shot->getInputs()['characterIds']);
        $empty = $inputs->resolve($episode, $shot, 0, 1);
        self::assertSame($a->getPortrait(), $empty['start']);
        self::assertSame([], $empty['subjectDetails']);
        self::assertSame([], $empty['subjects']);
        self::assertStringNotContainsString($b->getPortrait(), json_encode($empty, JSON_THROW_ON_ERROR));
    }

    public function testCrudStaleFormsArchiveAndPrivateImages(): void
    {
        $this->client->request('GET', '/studio/personnages');
        self::assertResponseIsSuccessful();
        $this->snap('empty');
        $this->client->request('GET', '/studio/personnages/nouveau');
        $this->snap('new');
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $this->client->request('GET', '/studio/personnages');
        $this->snap('list');
        self::assertSelectorTextContains('h2', 'Milo');
        self::assertSelectorCount(6, '.studio-bottom-nav > a');
        self::assertSelectorExists('.studio-bottom-nav a[href="/studio/creation"][aria-current="page"]');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/portrait');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $crawler = $this->client->request('GET', '/studio/personnages/'.$id);
        $this->snap('edit');
        $stale = $crawler->selectButton('Enregistrer le personnage')->form()->getPhpValues();
        $values = $stale;
        $values['ai_character']['name'] = 'Milo corrigé';
        $this->client->request('POST', '/studio/personnages/'.$id, $values, ['ai_character' => ['portrait' => $this->image(), 'views' => [$this->image()]]]);
        self::assertResponseStatusCodeSame(303);
        $character = $this->em->find(AiCharacter::class, $id);
        self::assertNotSame($old, $character->getPortrait());
        self::assertCount(1, $character->getViews());
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $owner = $this->em->find(User::class, $this->owner);
        self::assertFileExists($storage->path($owner, $old));
        copy($storage->path($owner, $character->getPortrait()), dirname(__DIR__, 2).'/artifacts/characters/portrait.jpg');
        $this->client->request('POST', '/studio/personnages/'.$id, $stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error-message, .form-errors', 'changé');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/0');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/studio/personnages');
        $this->client->submit($crawler->selectButton('Archiver')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorNotExists('.character-card');
        $crawler = $this->client->request('GET', '/studio/personnages?archives=1');
        $this->snap('archives');
        $restore = $crawler->selectButton('Restaurer')->form();
        $this->client->request('GET', '/studio/personnages/'.$id);
        self::assertSelectorNotExists('input[type=file]');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/portrait');
        self::assertResponseIsSuccessful();
        $this->client->submit($restore);
        self::assertResponseStatusCodeSame(303);
        $this->login($this->other);
        foreach (['/studio/personnages/'.$id, '/studio/personnages/'.$id.'/image/portrait'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/studio/personnages/'.$id, $stale);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/personnages/'.$id.'/archiver');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/personnages');
        self::assertSelectorNotExists('.character-card');
    }

    public function testInvalidFilesKeepTextAndRollbackEveryNewImage(): void
    {
        $crawler = $this->client->request('GET', '/studio/personnages/nouveau');
        $values = $crawler->selectButton('Enregistrer le personnage')->form(['ai_character[name]' => 'À conserver'])->getPhpValues();
        $this->client->request('POST', '/studio/personnages/nouveau', $values, ['ai_character' => ['portrait' => $this->image(), 'views' => [$this->image(false)]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('ai_character[name]', 'À conserver');
        $this->snap('invalid');
        self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->owner]));
        self::assertSame([], glob(dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner.'/*.jpg') ?: []);
        $values['ai_character']['_token'] = 'invalid';
        $this->client->request('POST', '/studio/personnages/nouveau', $values);
        self::assertResponseStatusCodeSame(422);
        $values['ai_character']['_token'] = $crawler->selectButton('Enregistrer le personnage')->form()->getPhpValues()['ai_character']['_token'];
        $this->client->request('POST', '/studio/personnages/nouveau', $values);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMaintenancePinsArchivedAndReplacedImages(): void
    {
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $owner = $this->em->find(User::class, $this->owner);
        $service = static::getContainer()->get(AiCharacters::class);
        $service->save($owner, $id, 1, 'Milo', 'Description', $this->image(), []);
        $service->archive($owner, $id, 2, true);
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $oldPath = $storage->path($owner, $old);
        touch($oldPath, time() - 40 * 86400);
        $orphan = $storage->store($owner, $this->image());
        $orphanPath = $storage->path($owner, $orphan);
        touch($orphanPath, time() - 40 * 86400);
        $sessions = sys_get_temp_dir().'/characters-'.bin2hex(random_bytes(6));
        mkdir($sessions);
        $this->temps[] = $sessions;
        $result = (new AiReferenceMaintenance($this->em, $storage, $sessions))->inspect($owner, true);
        self::assertSame(1, $result['files']);
        self::assertFileExists($oldPath);
        self::assertFileDoesNotExist($orphanPath);
        $this->client->request('POST', '/studio/personnages/'.$id.'/archiver', ['revision' => 3, 'archive' => 0, '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPaginationStaleArchiveAndFailedReplacementPreserveImages(): void
    {
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $owner = $this->em->find(User::class, $this->owner);
        $service = static::getContainer()->get(AiCharacters::class);
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $folder = dirname($storage->path($owner, $old));
        $before = glob($folder.'/*.jpg');
        try {
            $service->save($owner, $id, 1, 'Remplacement refusé', '', $this->image(), [$this->image(), $this->image(false)]);
            self::fail('The last invalid view must refuse the entire replacement.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('JPG', $error->getMessage());
        }
        self::assertSame($before, glob($folder.'/*.jpg'));
        $this->em->refresh($character);
        self::assertSame($old, $character->getPortrait());
        self::assertSame(1, $character->getRevision());
        $character = $service->save($owner, $id, 1, 'Milo', '', null, [$this->image()]);
        $view = $character->getViews()[0];
        $service->save($owner, $id, 2, 'Milo', '', null, [], true);
        self::assertSame([], $character->getViews());
        self::assertFileExists($storage->path($owner, $view));
        for ($i = 0; $i < 12; ++$i) {
            $this->em->persist(new AiCharacter($owner, 'Fiche '.$i, '<script>texte</script>', $old));
        }
        $this->em->flush();
        $crawler = $this->client->request('GET', '/studio/personnages');
        self::assertSelectorCount(12, '.character-card');
        $this->client->request('GET', '/studio/personnages?page=999');
        self::assertSelectorCount(1, '.character-card');
        self::assertSelectorTextContains('nav[aria-label="Pages des personnages"]', 'Page 2 sur 2');
        $this->client->request('GET', '/studio/personnages/'.$id);
        $crawler = $this->client->request('GET', '/studio/personnages?page=2');
        $form = $crawler->selectButton('Archiver')->form();
        $values = $form->getPhpValues();
        $values['revision'] = 1;
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertFalse($this->em->find(AiCharacter::class, $id)->isArchived());
        $this->client->restart();
        $this->client->request('GET', '/studio/personnages');
        self::assertResponseRedirects('http://localhost/connexion');
    }
}
