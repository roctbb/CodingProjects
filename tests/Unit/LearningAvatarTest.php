<?php

namespace Tests\Unit;

use App\Achievement;
use App\CoinTransaction;
use App\Course;
use App\CourseActivity;
use App\Program;
use App\User;
use Tests\TestCase;

class LearningAvatarTest extends TestCase
{
    public function testDefaultConfigOwnsFreeItemsOnly()
    {
        $config = User::defaultLearningAvatarConfig();

        $this->assertSame('room-system', $config['manifest']);
        $this->assertContains('basic_laptop', $config['owned']);
        $this->assertNotContains('premium_keyboard', $config['owned']);
        $this->assertNotContains('learning_bot_pet', $config['owned']);
        $this->assertSame('basic_laptop', $config['equipped']['desk_center']);

        $data = (new User())->learningAvatarRenderData();
        $this->assertSame('room-system', $data['config']['manifest']);
        $this->assertSame('room-system', $data['manifest']['key']);
    }

    public function testInvalidLearningAvatarManifestFallsBackToRoomSystem()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'missing-manifest',
            'owned' => ['basic_laptop'],
            'equipped' => [
                'desk_center' => 'basic_laptop',
            ],
        ];

        $config = $user->learningAvatarConfig();
        $data = $user->learningAvatarRenderData();

        $this->assertSame('room-system', $config['manifest']);
        $this->assertSame('room-system', $data['manifest']['key']);
        $this->assertSame('basic_laptop', $config['equipped']['desk_center']);
    }

    public function testPaidItemsCannotBeEquippedUntilOwned()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'equipped' => [
                'desk_center' => 'premium_keyboard',
                'pet_right' => 'learning_bot_pet',
            ],
        ];

        $config = $user->learningAvatarConfig();

        $this->assertArrayNotHasKey('desk_center', $config['equipped']);
        $this->assertArrayNotHasKey('pet_right', $config['equipped']);
    }

    public function testOwnedPaidItemsCanBeRendered()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['premium_keyboard', 'learning_bot_pet'],
            'equipped' => [
                'desk_center' => 'premium_keyboard',
                'pet_right' => 'learning_bot_pet',
            ],
        ];

        $config = $user->learningAvatarConfig();
        $layers = collect($user->learningAvatarRenderData()['layers'])->pluck('key')->all();

        $this->assertSame('premium_keyboard', $config['equipped']['desk_center']);
        $this->assertSame('learning_bot_pet', $config['equipped']['pet_right']);
        $this->assertContains('premium_keyboard', $layers);
        $this->assertContains('learning_bot_pet', $layers);
    }

    public function testOptionsExposeOwnedFlagsAndCosts()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['premium_keyboard'],
        ];

        $options = $user->learningAvatarOptionsBySlot();

        $this->assertTrue($options['desk_center']['premium_keyboard']['owned']);
        $this->assertSame(45, $options['desk_center']['premium_keyboard']['cost']);
        $this->assertFalse($options['pet_right']['learning_bot_pet']['owned']);
        $this->assertSame(90, $options['pet_right']['learning_bot_pet']['cost']);
    }

    public function testCoinTransactionDisplaysLearningAvatarItemName()
    {
        $transaction = new CoinTransaction();
        $transaction->comment = 'Learning avatar item premium_keyboard User #7';

        $this->assertSame('Предмет комнаты "Механическая клавиатура"', $transaction->displayComment());
    }

    public function testPurchasePlanCalculatesNewPaidItemsOnce()
    {
        $user = new User();

        $plan = $user->learningAvatarPurchasePlan('room-system', [
            'desk_center' => 'premium_keyboard',
            'pet_right' => 'learning_bot_pet',
        ]);

        $this->assertSame(135, $plan['totalCost']);
        $this->assertSame(['premium_keyboard', 'learning_bot_pet'], array_keys($plan['itemsToBuy']));
    }

    public function testPurchasePlanIgnoresAlreadyOwnedPaidItems()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['premium_keyboard'],
        ];

        $plan = $user->learningAvatarPurchasePlan('room-system', [
            'desk_center' => 'premium_keyboard',
            'pet_right' => 'learning_bot_pet',
        ]);

        $this->assertSame(90, $plan['totalCost']);
        $this->assertArrayNotHasKey('premium_keyboard', $plan['itemsToBuy']);
        $this->assertArrayHasKey('learning_bot_pet', $plan['itemsToBuy']);
    }

    public function testPurchasePlanIgnoresItemsSubmittedForWrongSlot()
    {
        $user = new User();

        $plan = $user->learningAvatarPurchasePlan('room-system', [
            'pet_right' => 'premium_keyboard',
            'desk_center' => 'learning_bot_pet',
        ]);

        $config = $user->learningAvatarConfigFromPurchasePlan($plan);

        $this->assertSame(0, $plan['totalCost']);
        $this->assertArrayNotHasKey('premium_keyboard', $plan['itemsToBuy']);
        $this->assertArrayNotHasKey('desk_center', $config['equipped']);
        $this->assertArrayNotHasKey('pet_right', $config['equipped']);
    }

    public function testConfigFromPurchasePlanAddsBoughtItemsToOwned()
    {
        $user = new User();
        $plan = $user->learningAvatarPurchasePlan('room-system', [
            'desk_center' => 'premium_keyboard',
        ]);

        $config = $user->learningAvatarConfigFromPurchasePlan($plan);

        $this->assertContains('premium_keyboard', $config['owned']);
        $this->assertSame('premium_keyboard', $config['equipped']['desk_center']);
    }

    public function testPetItemCanBePurchasedAndRendered()
    {
        $user = new User();
        $plan = $user->learningAvatarPurchasePlan('room-system', [
            'pet_right' => 'learning_bot_pet',
        ]);

        $config = $user->learningAvatarConfigFromPurchasePlan($plan);
        $user->learning_avatar_config = $config;
        $layers = collect($user->learningAvatarRenderData()['layers'])->keyBy('equippedSlot');

        $this->assertSame(90, $plan['totalCost']);
        $this->assertContains('learning_bot_pet', $config['owned']);
        $this->assertSame('learning_bot_pet', $config['equipped']['pet_right']);
        $this->assertStringContainsString('pets/learning_bot.png', $layers['pet_right']['src']);
    }

    public function testComponentDoesNotRenderLockedPaidItem()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'grade' => 7,
            ],
            'equipped' => [
                'desk_center' => 'premium_keyboard',
            ],
        ];

        $html = view('components.gc-learning-avatar', ['user' => $user])->render();

        $this->assertStringNotContainsString('premium_keyboard.png', $html);
        $this->assertStringContainsString('rooms/room_01_home_start.png', $html);
        $this->assertStringContainsString('characters/boy/class_07.png', $html);
    }

    public function testComponentRendersOwnedPaidItem()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['premium_keyboard'],
            'equipped' => [
                'desk_center' => 'premium_keyboard',
            ],
        ];

        $html = view('components.gc-learning-avatar', ['user' => $user])->render();

        $this->assertStringContainsString('premium_keyboard.png', $html);
    }

    public function testRenderDataContainsPreviewItemsForLivePreview()
    {
        $user = new User();
        $data = $user->learningAvatarRenderData();

        $this->assertArrayHasKey('previewItemsBySlot', $data);
        $this->assertSame('premium_keyboard', $data['previewItemsBySlot']['desk_center']['premium_keyboard']['key']);
        $this->assertSame('desk_center', $data['previewItemsBySlot']['desk_center']['premium_keyboard']['equippedSlot']);
        $this->assertStringContainsString('premium_keyboard.png', $data['previewItemsBySlot']['desk_center']['premium_keyboard']['src']);
        $this->assertSame('pet_right', $data['previewItemsBySlot']['pet_right']['learning_bot_pet']['equippedSlot']);
    }

    public function testRenderDataExposesLayerOrderForLivePreview()
    {
        $user = new User();
        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame(4, $data['renderOrder']['desk_center']);
        $this->assertSame(5, $data['renderOrder']['trophies']);
        $this->assertSame(6, $data['renderOrder']['character']);
        $this->assertLessThan($layers['character']['order'], $layers['desk_center']['order']);
        $this->assertSame(7, $data['renderOrder']['pet_right']);
    }

    public function testGenderSelectsCharacterSkin()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'gender' => 'girl',
                'grade' => 7,
            ],
        ];

        $layers = collect($user->learningAvatarRenderData()['layers'])->keyBy('equippedSlot');

        $this->assertStringContainsString('characters/girl/class_07.png', $layers['character']['src']);
    }

    public function testProfileGenderOverridesLearningAvatarConfigGender()
    {
        $user = new User();
        $user->gender = 'girl';
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'gender' => 'boy',
                'grade' => 7,
            ],
        ];

        $config = $user->learningAvatarConfig();
        $layers = collect($user->learningAvatarRenderData()['layers'])->keyBy('equippedSlot');

        $this->assertSame('girl', $config['appearance']['gender']);
        $this->assertStringContainsString('characters/girl/class_07.png', $layers['character']['src']);
    }

    public function testRankStageChangesCharacterAndRoom()
    {
        $user = new User();
        $user->setComputedScore(900);
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'grade' => 7,
            ],
        ];

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('skilled', $data['appearance']['legacyRankStage']);
        $this->assertSame('room_05_hackathon_zone', $data['appearance']['rankStage']);
        $this->assertStringContainsString('rooms/room_05_hackathon_zone.png', $layers['room']['src']);
        $this->assertStringContainsString('characters/boy/class_07.png', $layers['character']['src']);
    }

    public function testRankStagesUseCurrentRankScoreScale()
    {
        $user = new User();

        $user->setComputedScore(0);
        $this->assertSame('novice', $user->learningAvatarRankStageKey());

        $user->setComputedScore(700);
        $this->assertSame('skilled', $user->learningAvatarRankStageKey());

        $user->setComputedScore(3500);
        $this->assertSame('advanced', $user->learningAvatarRankStageKey());

        $user->setComputedScore(7500);
        $this->assertSame('expert', $user->learningAvatarRankStageKey());

        $user->setComputedScore(16500);
        $this->assertSame('master', $user->learningAvatarRankStageKey());
    }

    public function testCourseEnrollmentSelectsPoster()
    {
        $user = new User();
        $program = new Program([
            'name' => 'Python Start',
            'learning_avatar_poster' => '/media/course-posters/python-start.png',
        ]);
        $program->id = 7;
        $course = new Course([
            'name' => 'Python Start',
            'state' => 'started',
        ]);
        $course->id = 42;
        $course->setRelation('program', $program);

        $user->setRelation('courses', collect([
            $course,
        ]));

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('program_7', $data['appearance']['coursePoster']);
        $this->assertSame('Python Start', $data['appearance']['coursePosterName']);
        $this->assertStringContainsString('/media/course-posters/python-start.png', $layers['course_poster']['src']);
    }

    public function testCourseEnrollmentWithoutGeneratedPosterUsesDefaultPoster()
    {
        $user = new User();
        $user->setRelation('courses', collect([
            new Course(['name' => 'Python Start', 'state' => 'started']),
        ]));

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('default', $data['appearance']['coursePoster']);
        $this->assertSame('Базовый курс', $data['appearance']['coursePosterName']);
        $this->assertStringContainsString('posters/default.png', $layers['course_poster']['src']);
    }

    public function testRoomSystemUsesLatestActiveCoursePoster()
    {
        $olderProgram = new Program([
            'name' => 'Web Start',
            'learning_avatar_poster' => '/media/course-posters/web-start.png',
        ]);
        $olderProgram->id = 5;
        $olderCourse = new Course([
            'name' => 'Web Start',
            'state' => 'started',
        ]);
        $olderCourse->id = 10;
        $olderCourse->setRelation('program', $olderProgram);

        $latestProgram = new Program([
            'name' => 'Robotics Pro',
            'learning_avatar_poster' => '/media/course-posters/robotics-pro.png',
        ]);
        $latestProgram->id = 9;
        $latestCourse = new Course([
            'name' => 'Robotics Pro',
            'state' => 'started',
        ]);
        $latestCourse->id = 15;
        $latestCourse->setRelation('program', $latestProgram);

        $draftProgram = new Program([
            'name' => 'Future Draft',
            'learning_avatar_poster' => '/media/course-posters/future-draft.png',
        ]);
        $draftProgram->id = 11;
        $draftCourse = new Course([
            'name' => 'Future Draft',
            'state' => 'draft',
        ]);
        $draftCourse->id = 20;
        $draftCourse->setRelation('program', $draftProgram);

        $user = new User();
        $user->setRelation('courses', collect([$olderCourse, $latestCourse, $draftCourse]));

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('program_9', $data['appearance']['coursePoster']);
        $this->assertSame('Robotics Pro', $data['appearance']['coursePosterName']);
        $this->assertStringContainsString('/media/course-posters/robotics-pro.png', $layers['course_poster']['src']);
    }

    public function testPublishedAchievementsRenderUpToThreeTrophies()
    {
        $user = new User();
        $user->setRelation('achievements', collect([
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Код', 'payload' => ['trophy_image' => '/media/achievement-trophies/code.png']]),
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Мозг', 'payload' => ['trophy_image' => '/media/achievement-trophies/brain.png']]),
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Искра', 'payload' => ['trophy_image' => '/media/achievement-trophies/spark.png']]),
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Скрыто', 'payload' => ['trophy_image' => '/media/achievement-trophies/hidden.png']]),
        ]));

        $layers = collect($user->learningAvatarRenderData()['layers'])
            ->where('role', 'achievement_trophy')
            ->values();

        $this->assertCount(3, $layers);
        $this->assertSame('shelf_trophy_1', $layers[0]['equippedSlot']);
        $this->assertSame('shelf_trophy_2', $layers[1]['equippedSlot']);
        $this->assertSame('shelf_trophy_3', $layers[2]['equippedSlot']);
        $this->assertStringContainsString('/media/achievement-trophies/code.png', $layers[0]['src']);
        $this->assertStringContainsString('/media/achievement-trophies/brain.png', $layers[1]['src']);
        $this->assertStringContainsString('/media/achievement-trophies/spark.png', $layers[2]['src']);
        $this->assertSame('custom', $layers[0]['trophyTemplate']);
    }

    public function testPublishedAchievementCustomTrophyImageOverridesTemplate()
    {
        $achievement = new Achievement([
            'status' => Achievement::STATUS_PUBLISHED,
            'title' => 'Квантовый сантехник',
            'icon_key' => 'code',
            'payload' => [
                'visual_key' => 'pipes',
                'trophy_image' => '/media/achievement-trophies/quantum-plumber.png',
            ],
        ]);
        $achievement->id = 77;

        $user = new User();
        $user->setRelation('achievements', collect([$achievement]));

        $layers = collect($user->learningAvatarRenderData()['layers'])
            ->where('role', 'achievement_trophy')
            ->values();

        $this->assertCount(1, $layers);
        $this->assertStringContainsString('/media/achievement-trophies/quantum-plumber.png', $layers[0]['src']);
        $this->assertSame('custom', $layers[0]['trophyTemplate']);
    }

    public function testRenderDataContainsWeatherAndSafeLayers()
    {
        $user = new User();
        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertArrayHasKey('window_weather', $layers);
        $this->assertStringContainsString('weather/', $layers['window_weather']['src']);
        $this->assertArrayHasKey('safe', $layers);
        $this->assertStringContainsString('safes/safe_empty.png', $layers['safe']['src']);
        $this->assertSame('safe_empty', $data['appearance']['safeStage']);
        $this->assertSame('window_weather', $layers['window_weather']['slot']);
        $this->assertFalse($layers['window_weather']['fullCanvas']);
        $this->assertSame('fill', $layers['window_weather']['fit']);
        $this->assertStringContainsString('left:', $layers['window_weather']['style']);
        $this->assertStringContainsString('width:', $layers['window_weather']['style']);
    }

    public function testComponentMarksItemLayersByEquippedSlot()
    {
        $user = new User();
        $user->setRelation('achievements', collect([
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Код', 'payload' => ['trophy_image' => '/media/achievement-trophies/code.png']]),
        ]));

        $html = view('components.gc-learning-avatar', ['user' => $user])->render();

        $this->assertStringContainsString('data-learning-avatar-layer-slot="desk_center"', $html);
        $this->assertStringContainsString('data-learning-avatar-layer-order="3"', $html);
        $this->assertStringContainsString('data-learning-avatar-layer-slot="window_weather"', $html);
        $this->assertStringContainsString('data-learning-avatar-layer-slot="safe"', $html);
        $this->assertStringContainsString('data-learning-avatar-layer-slot="shelf_trophy_1"', $html);
        $this->assertStringNotContainsString('data-learning-avatar-layer-slot="floor_right"', $html);
        $this->assertStringNotContainsString('data-learning-avatar-layer-slot="fx"', $html);
    }

    public function testComponentRendersRoomSystemCharacterScaleStyle()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'gender' => 'girl',
                'grade' => 11,
            ],
        ];

        $html = view('components.gc-learning-avatar', ['user' => $user])->render();

        $this->assertStringContainsString('characters/girl/class_11.png', $html);
        $this->assertStringContainsString('object-fit: cover', $html);
        $this->assertStringContainsString('object-position: center bottom', $html);
        $this->assertStringContainsString('transform: scale(1.18)', $html);
        $this->assertStringContainsString('transform-origin: center bottom', $html);
    }

    public function testRoomSystemManifestRendersNewLayerStack()
    {
        $user = new User();
        $user->setComputedScore(900);
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['basic_laptop', 'learning_bot_pet'],
            'appearance' => [
                'gender' => 'girl',
                'grade' => 6,
            ],
            'equipped' => [
                'desk_center' => 'basic_laptop',
                'pet_right' => 'learning_bot_pet',
            ],
        ];

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('room-system', $data['config']['manifest']);
        $this->assertSame('room_05_hackathon_zone', $data['appearance']['rankStage']);
        $this->assertSame('skilled', $data['appearance']['legacyRankStage']);
        $this->assertSame('room_05_hackathon_zone', $data['appearance']['roomKey']);
        $this->assertSame(6, $data['appearance']['grade']);
        $this->assertSame('Базовый курс', $data['appearance']['coursePosterName']);
        $this->assertNotEmpty($data['appearance']['weatherLayerName']);
        $this->assertStringContainsString('room-system/weather/', $layers['window_weather']['src']);
        $this->assertStringContainsString('season_', $data['appearance']['weatherLayer']);
        $this->assertSame('window_weather', $layers['window_weather']['slot']);
        $this->assertStringContainsString('width:', $layers['window_weather']['style']);
        $this->assertStringContainsString('left:', $layers['window_weather']['style']);
        $this->assertStringContainsString('height:', $layers['window_weather']['style']);
        $this->assertSame('fill', $layers['window_weather']['fit']);
        $this->assertStringContainsString('room-system/posters/', $layers['course_poster']['src']);
        $this->assertStringContainsString('rooms/room_05_hackathon_zone.png', $layers['room']['src']);
        $this->assertStringContainsString('characters/girl/class_06.png', $layers['character']['src']);
        $this->assertSame('cover', $layers['character']['fit']);
        $this->assertSame('center bottom', $layers['character']['objectPosition']);
        $this->assertStringContainsString('scale(0.88)', $layers['character']['innerStyle']);
        $this->assertStringContainsString('pets/learning_bot.png', $layers['pet_right']['src']);
        $this->assertArrayHasKey('characterPreviewByGradeAndGender', $data);
        $this->assertStringContainsString('characters/boy/class_05.png', $data['characterPreviewByGradeAndGender'][5]['boy']['src']);
        $this->assertStringContainsString('characters/girl/class_11.png', $data['characterPreviewByGradeAndGender'][11]['girl']['src']);
        $this->assertStringContainsString('characters/boy/student.png', $data['characterPreviewByGradeAndGender'][12]['boy']['src']);
        $this->assertStringContainsString('characters/girl/student.png', $data['characterPreviewByGradeAndGender'][12]['girl']['src']);
        $this->assertStringContainsString('scale(0.82)', $data['characterPreviewByGradeAndGender'][5]['boy']['innerStyle']);
        $this->assertStringContainsString('scale(1.18)', $data['characterPreviewByGradeAndGender'][11]['girl']['innerStyle']);
        $this->assertStringContainsString('scale(1.22)', $data['characterPreviewByGradeAndGender'][12]['girl']['innerStyle']);
    }

    public function testGraduateGradeUsesStudentCharacter()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'gender' => 'girl',
                'grade' => 12,
            ],
        ];

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame(12, $data['appearance']['grade']);
        $this->assertStringContainsString('characters/girl/student.png', $layers['character']['src']);
        $this->assertStringContainsString('scale(1.22)', $layers['character']['innerStyle']);
    }

    public function testGraduateTeacherUsesTeacherCharacter()
    {
        $user = new User();
        $user->role = 'teacher';
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'gender' => 'boy',
                'grade' => 12,
            ],
        ];

        $layers = collect($user->learningAvatarRenderData()['layers'])->keyBy('equippedSlot');

        $this->assertStringContainsString('characters/boy/teacher.png', $layers['character']['src']);

        $data = $user->learningAvatarRenderData();
        $this->assertStringContainsString('characters/boy/teacher.png', $data['characterPreviewByGradeAndGender'][12]['boy']['src']);
        $this->assertStringContainsString('characters/girl/teacher.png', $data['characterPreviewByGradeAndGender'][12]['girl']['src']);
    }

    public function testGradeLabelShowsGraduateForTwelfthGrade()
    {
        $user = new User();
        $user->setGrade(12);

        $this->assertSame(12, $user->grade());
        $this->assertSame('Выпускник', $user->gradeLabel());
    }

    public function testRoomSystemUsesFullRoomProgressionFromScore()
    {
        $cases = [
            0 => 'room_01_home_start',
            50 => 'room_02_home_practice',
            100 => 'room_03_coding_club',
            300 => 'room_04_maker_workshop',
            700 => 'room_05_hackathon_zone',
            1500 => 'room_06_algorithm_gym',
            2500 => 'room_07_project_auditorium',
            3500 => 'room_08_university_lab',
            4500 => 'room_09_glass_office',
            5500 => 'room_10_social_product_office',
            6500 => 'room_11_search_ml_office',
            7500 => 'room_12_rd_office',
            10500 => 'room_13_datacenter',
            13500 => 'room_14_command_center',
            16500 => 'room_15_cto_director_office',
            20500 => 'room_16_technopark_director',
            25500 => 'room_17_president_office',
        ];

        foreach ($cases as $score => $expectedRoomKey) {
            $user = new User();
            $user->setComputedScore($score);
            $user->learning_avatar_config = ['manifest' => 'room-system'];

            $data = $user->learningAvatarRenderData();
            $layers = collect($data['layers'])->keyBy('equippedSlot');

            $this->assertSame($expectedRoomKey, $data['appearance']['roomKey']);
            $this->assertSame($expectedRoomKey, $data['appearance']['rankStage']);
            $this->assertStringContainsString('rooms/' . $expectedRoomKey . '.png', $layers['room']['src']);
        }
    }

    public function testRoomSystemUsesCoursePosterAndAchievementTrophies()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'appearance' => [
                'grade' => 7,
            ],
        ];
        $program = new Program([
            'name' => 'Python Start',
            'learning_avatar_poster' => '/media/course-posters/python-start.png',
        ]);
        $program->id = 7;
        $course = new Course([
            'name' => 'Python Start',
            'state' => 'started',
        ]);
        $course->id = 42;
        $course->setRelation('program', $program);
        $user->setRelation('courses', collect([
            $course,
        ]));
        $user->setRelation('achievements', collect([
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Код', 'payload' => ['trophy_image' => '/media/achievement-trophies/code.png']]),
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Мозг', 'payload' => ['trophy_image' => '/media/achievement-trophies/brain.png']]),
            new Achievement(['status' => Achievement::STATUS_PUBLISHED, 'title' => 'Искра', 'payload' => ['trophy_image' => '/media/achievement-trophies/spark.png']]),
        ]));

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');

        $this->assertSame('program_7', $data['appearance']['coursePoster']);
        $this->assertSame('Python Start', $data['appearance']['coursePosterName']);
        $this->assertStringContainsString('/media/course-posters/python-start.png', $layers['course_poster']['src']);
        $this->assertStringContainsString('/media/achievement-trophies/code.png', $layers['shelf_trophy_1']['src']);
        $this->assertStringContainsString('/media/achievement-trophies/brain.png', $layers['shelf_trophy_2']['src']);
        $this->assertStringContainsString('/media/achievement-trophies/spark.png', $layers['shelf_trophy_3']['src']);
        $this->assertLessThan($layers['character']['order'], $layers['shelf_trophy_1']['order']);
        $this->assertLessThan($layers['character']['order'], $layers['shelf_trophy_2']['order']);
        $this->assertLessThan($layers['character']['order'], $layers['shelf_trophy_3']['order']);
    }

    public function testRoomSystemCatalogSupportsDeskAndPetVariants()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['code_mug', 'brr_brr_patapim_pet'],
            'equipped' => [
                'desk_center' => 'code_mug',
                'pet_right' => 'brr_brr_patapim_pet',
            ],
        ];

        $data = $user->learningAvatarRenderData();
        $layers = collect($data['layers'])->keyBy('equippedSlot');
        $options = $data['optionsBySlot'];

        $this->assertSame(25, $options['desk_center']['code_mug']['cost']);
        $this->assertSame(90, $options['pet_right']['learning_bot_pet']['cost']);
        $this->assertSame(120, $options['pet_right']['cheremsha_pet']['cost']);
        $this->assertSame(260, $options['pet_right']['brr_brr_patapim_pet']['cost']);
        $this->assertSame(300, $options['pet_right']['tralalelo_tralala_pet']['cost']);
        $this->assertSame(240, $options['pet_right']['tung_tung_tung_sahur_pet']['cost']);
        $this->assertStringContainsString('items/code_mug.png', $layers['desk_center']['src']);
        $this->assertStringContainsString('pets/brr_brr_patapim.png', $layers['pet_right']['src']);
    }

    public function testDigitalStoreExposesPetAbilities()
    {
        $user = new User();
        $user->learning_avatar_config = ['manifest' => 'room-system'];

        $items = $user->learningAvatarDigitalStoreItems()->keyBy('key');

        $this->assertTrue($items->has('learning_bot_pet'));
        $this->assertTrue($items->has('cheremsha_pet'));
        $this->assertTrue($items->has('brr_brr_patapim_pet'));
        $this->assertTrue($items->has('tralalelo_tralala_pet'));
        $this->assertTrue($items->has('tung_tung_tung_sahur_pet'));
        $this->assertCount(1, $items['learning_bot_pet']['abilities']);
        $this->assertCount(1, $items['cheremsha_pet']['abilities']);
        $this->assertCount(1, $items['brr_brr_patapim_pet']['abilities']);
        $this->assertCount(1, $items['tralalelo_tralala_pet']['abilities']);
        $this->assertCount(1, $items['tung_tung_tung_sahur_pet']['abilities']);
        $this->assertSame(['daily_free_geekpaste_reset'], $items['learning_bot_pet']['abilities']);
        $this->assertStringContainsString('pets/learning_bot.png', $items['learning_bot_pet']['preview_src']);
        $this->assertSame(['daily_coin_gift'], $items['cheremsha_pet']['abilities']);
        $this->assertSame(30, User::learningAvatarPetDailyActionChance('cheremsha_pet'));
        $this->assertStringContainsString('30%', implode(' ', $items['cheremsha_pet']['ability_descriptions']));
        $this->assertSame(['early_lesson_unlock'], $items['brr_brr_patapim_pet']['abilities']);
        $this->assertNotEmpty($items['brr_brr_patapim_pet']['ability_descriptions']);
        $this->assertStringContainsString('бесплатно открывать', implode(' ', $items['brr_brr_patapim_pet']['ability_descriptions']));
        $this->assertSame(['task_coin_bonus_10'], $items['tralalelo_tralala_pet']['abilities']);
        $this->assertStringContainsString('10%', implode(' ', $items['tralalelo_tralala_pet']['ability_descriptions']));
        $this->assertSame(['daily_auto_xp_booster'], $items['tung_tung_tung_sahur_pet']['abilities']);
        $this->assertStringContainsString('автоматически', implode(' ', $items['tung_tung_tung_sahur_pet']['ability_descriptions']));
        $this->assertStringContainsString('pets/tung_tung_tung_sahur.png', $items['tung_tung_tung_sahur_pet']['preview_src']);
    }

    public function testDigitalStoreIncludesProfileCosmetics()
    {
        $user = new User();
        $user->learning_avatar_config = ['manifest' => 'room-system'];

        $items = $user->digitalStoreItems()->keyBy('key');

        $this->assertSame('custom_title', $items['custom_title']['type']);
        $this->assertSame($user->customTitleCost(), $items['custom_title']['cost']);
        $this->assertSame('avatar_frame', $items['avatar_frame_neon']['type']);
        $this->assertSame('custom_avatar_frame', $items['avatar_frame_custom']['type']);
        $this->assertSame($user->customAvatarFrameCost(false), $items['avatar_frame_custom']['cost']);
        $this->assertSame($user->customAvatarFrameCost(true), $items['avatar_frame_custom']['animated_cost']);
        $this->assertTrue($items->has('learning_bot_pet'));
        $this->assertStringContainsString('items/code_mug.png', $items['code_mug']['preview_src']);
    }

    public function testActivePetAddsTaskCoinBonus()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['tralalelo_tralala_pet'],
            'equipped' => [
                'pet_right' => 'tralalelo_tralala_pet',
            ],
        ];

        $this->assertSame(11, $user->taskCoinReward(10));
        $this->assertSame(2, $user->taskCoinReward(1));
    }

    public function testBrrBrrPatapimMakesEarlyLessonAccessFree()
    {
        $lesson = new \App\Lesson();
        $user = new User();

        $this->assertSame(10, $user->earlyLessonAccessCost($lesson));

        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['brr_brr_patapim_pet'],
            'equipped' => [
                'pet_right' => 'brr_brr_patapim_pet',
            ],
        ];

        $this->assertTrue($user->canUseFreeEarlyLessonAccess());
        $this->assertSame(0, $user->earlyLessonAccessCost($lesson));
    }

    public function testFreeXpBoosterMakesSolutionBoosterFree()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'pet_bonuses' => [
                'free_xp_boosters' => 1,
            ],
        ];
        $solution = new \App\Solution();

        $this->assertSame(1, $user->freeXpBoostersCount());
        $this->assertSame(0, $solution->xpBoosterCost($user));
    }

    public function testRobopetAllowsOneFreeGeekPasteResetPerDay()
    {
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['learning_bot_pet'],
            'equipped' => [
                'pet_right' => 'learning_bot_pet',
            ],
        ];

        $this->assertSame(0, $user->geekPasteExtraAttemptCost());

        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => ['learning_bot_pet'],
            'equipped' => [
                'pet_right' => 'learning_bot_pet',
            ],
            'pet_bonuses' => [
                'free_geekpaste_reset_used_on' => \Carbon\Carbon::now()->toDateString(),
            ],
        ];

        $this->assertSame(\App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST, $user->geekPasteExtraAttemptCost());
    }

    public function testOnlyEquippedPetAbilityAffectsRoomBonuses()
    {
        $lesson = new \App\Lesson();
        $allPets = array_keys(User::learningAvatarPetAbilities());

        $cases = [
            'learning_bot_pet' => [
                'ability' => 'daily_free_geekpaste_reset',
                'geekpaste_cost' => 0,
                'early_lesson_cost' => 10,
                'task_reward' => 10,
                'auto_xp' => false,
            ],
            'cheremsha_pet' => [
                'ability' => 'daily_coin_gift',
                'geekpaste_cost' => \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST,
                'early_lesson_cost' => 10,
                'task_reward' => 10,
                'auto_xp' => false,
            ],
            'brr_brr_patapim_pet' => [
                'ability' => 'early_lesson_unlock',
                'geekpaste_cost' => \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST,
                'early_lesson_cost' => 0,
                'task_reward' => 10,
                'auto_xp' => false,
            ],
            'tralalelo_tralala_pet' => [
                'ability' => 'task_coin_bonus_10',
                'geekpaste_cost' => \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST,
                'early_lesson_cost' => 10,
                'task_reward' => 11,
                'auto_xp' => false,
            ],
            'tung_tung_tung_sahur_pet' => [
                'ability' => 'daily_auto_xp_booster',
                'geekpaste_cost' => \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST,
                'early_lesson_cost' => 10,
                'task_reward' => 10,
                'auto_xp' => true,
            ],
        ];

        foreach ($cases as $petKey => $expectation) {
            $user = new User();
            $user->learning_avatar_config = [
                'manifest' => 'room-system',
                'owned' => $allPets,
                'equipped' => [
                    'pet_right' => $petKey,
                ],
            ];

            $this->assertSame($petKey, $user->activeLearningAvatarPetKey(), $petKey);

            foreach (User::learningAvatarPetAbilities() as $candidateAbilities) {
                foreach ($candidateAbilities as $ability) {
                    $this->assertSame(
                        $ability === $expectation['ability'],
                        $user->hasActiveLearningAvatarPetAbility($ability),
                        $petKey . ' / ' . $ability
                    );
                }
            }

            $this->assertSame($expectation['geekpaste_cost'], $user->geekPasteExtraAttemptCost(), $petKey);
            $this->assertSame($expectation['early_lesson_cost'], $user->earlyLessonAccessCost($lesson), $petKey);
            $this->assertSame($expectation['task_reward'], $user->taskCoinReward(10), $petKey);
            $this->assertSame($expectation['auto_xp'], $user->canUseDailyAutoXpBoosterToday(), $petKey);
        }
    }

    public function testOwnedButNotEquippedPetsDoNotApplyAbilities()
    {
        $lesson = new \App\Lesson();
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => array_keys(User::learningAvatarPetAbilities()),
            'equipped' => [],
        ];

        $this->assertNull($user->activeLearningAvatarPetKey());
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('daily_free_geekpaste_reset'));
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('daily_coin_gift'));
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('early_lesson_unlock'));
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('task_coin_bonus_10'));
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('daily_auto_xp_booster'));
        $this->assertSame(\App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST, $user->geekPasteExtraAttemptCost());
        $this->assertSame(10, $user->earlyLessonAccessCost($lesson));
        $this->assertSame(10, $user->taskCoinReward(10));
        $this->assertFalse($user->canUseDailyAutoXpBoosterToday());
    }

    public function testUnownedEquippedPetDoesNotApplyAbility()
    {
        $lesson = new \App\Lesson();
        $user = new User();
        $user->learning_avatar_config = [
            'manifest' => 'room-system',
            'owned' => [],
            'equipped' => [
                'pet_right' => 'tralalelo_tralala_pet',
            ],
        ];

        $this->assertNull($user->activeLearningAvatarPetKey());
        $this->assertFalse($user->hasActiveLearningAvatarPetAbility('task_coin_bonus_10'));
        $this->assertSame(\App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST, $user->geekPasteExtraAttemptCost());
        $this->assertSame(10, $user->earlyLessonAccessCost($lesson));
        $this->assertSame(10, $user->taskCoinReward(10));
        $this->assertFalse($user->canUseDailyAutoXpBoosterToday());
    }

    public function testPetAssistedActionsAreLabeledInPulse()
    {
        $xpBoosterActivity = new CourseActivity([
            'type' => CourseActivity::TYPE_XP_BOOSTER_USED,
            'payload' => [
                'amount' => 4,
                'pet_name' => 'Тунг Тунг Тунг Сахур',
            ],
        ]);

        $earlyAccessActivity = new CourseActivity([
            'type' => CourseActivity::TYPE_EARLY_ACCESS_BOUGHT,
            'payload' => [
                'lesson_name' => 'Массивы',
                'cost' => 0,
                'pet_name' => 'Брр Брр Потапим',
            ],
        ]);

        $geekPasteActivity = new CourseActivity([
            'type' => CourseActivity::TYPE_GEEKPASTE_ATTEMPT_BOUGHT,
            'payload' => [
                'cost' => 0,
                'pet_name' => 'Робопес',
            ],
        ]);

        $this->assertSame('усилил(а) решение с помощью питомца «Тунг Тунг Тунг Сахур»', $xpBoosterActivity->actionText());
        $this->assertSame('открыл(а) урок «Массивы» раньше с помощью питомца «Брр Брр Потапим»', $earlyAccessActivity->actionText());
        $this->assertSame('получил(а) бесплатную попытку GeekPaste от питомца «Робопес»', $geekPasteActivity->actionText());
    }

    public function testDailyPetActionsHavePulsePresentation()
    {
        $activity = new CourseActivity([
            'type' => CourseActivity::TYPE_PET_ACTION,
            'payload' => [
                'course_name' => 'Python Start',
                'pet_action' => 'daily_coin_gift',
                'pet_name' => 'Черемша',
                'amount' => 3,
            ],
        ]);

        $this->assertSame('получил(а) 3 GC от питомца «Черемша»', $activity->actionText());
        $this->assertSame('Python Start · +3 GC', $activity->subtitle());
        $this->assertSame('fas fa-paw', $activity->iconClass());
        $this->assertSame('is-boost', $activity->toneClass());
    }
}
