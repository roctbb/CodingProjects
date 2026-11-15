<?php
// A large roster with long names, isolated from all other browser flows.
foreach (['statistics-desktop', 'statistics-phone'] as $variant) {
    copy($directory.'/base.sqlite', $directory.'/'.$variant.'.sqlite');
    $db = new PDO('sqlite:'.$directory.'/'.$variant.'.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('DELETE FROM course_students WHERE course_id=1');
    $insertUser = $db->prepare('INSERT INTO users (id,name,role,email,email_verified_at) VALUES (?,?,\'student\',?,\'2026-09-01\')');
    $enroll = $db->prepare('INSERT INTO course_students (course_id,user_id) VALUES (1,?)');
    $stats = $db->prepare('INSERT INTO lesson_student_stats (course_id,lesson_id,student_id,points,max_points,percent) VALUES (1,?,?,?,?,?)');
    $db->exec('DELETE FROM lesson_student_stats WHERE course_id=1');
    $lessons = $db->query('SELECT id FROM lessons WHERE program_id=1')->fetchAll(PDO::FETCH_COLUMN);
    foreach ([1,3,4,...range(100,166)] as $id) {
        if ($id >= 100) $insertUser->execute([$id, match ($id) {
            100 => 'Александрова-Рождественская Александра Константиновна',
            101 => 'Александров-Рождественский Александр Константинович',
            default => sprintf('Ученик %03d', $id),
        }, 'statistics-'.$id.'@example.test']);
        $enroll->execute([$id]);
        foreach ($lessons as $lesson) {
            $points = $id === 100 ? 20 : ($id % 5) * 4;
            $stats->execute([$lesson,$id,$points,20,$points*5]);
        }
    }
}
