-- Performance indexes for /insider/courses optimization
-- Run this file to add missing indexes

-- Courses table
ALTER TABLE courses ADD INDEX IF NOT EXISTS idx_state (state);
ALTER TABLE courses ADD INDEX IF NOT EXISTS idx_is_open (is_open);
ALTER TABLE courses ADD INDEX IF NOT EXISTS idx_provider (provider_id);

-- Solutions table (for bulk loading submissions)
ALTER TABLE solutions ADD INDEX IF NOT EXISTS idx_user_task (user_id, task_id);
ALTER TABLE solutions ADD INDEX IF NOT EXISTS idx_task_user_mark (task_id, user_id, mark);

-- Course relationships
ALTER TABLE course_student ADD INDEX IF NOT EXISTS idx_course_user (course_id, user_id);
ALTER TABLE course_teacher ADD INDEX IF NOT EXISTS idx_course_user (course_id, user_id);

-- Lessons table
ALTER TABLE lessons ADD INDEX IF NOT EXISTS idx_course_started (course_id, start_date);
ALTER TABLE lessons ADD INDEX IF NOT EXISTS idx_chapter_sort (chapter_id, sort_index);
ALTER TABLE lessons ADD INDEX IF NOT EXISTS idx_program (program_id);

-- Action logs (for pulse/activity tracking)
ALTER TABLE action_logs ADD INDEX IF NOT EXISTS idx_user_created (user_id, created_at);
ALTER TABLE action_logs ADD INDEX IF NOT EXISTS idx_type_object (type, object_id);
ALTER TABLE action_logs ADD INDEX IF NOT EXISTS idx_created (created_at);

-- Completed courses
ALTER TABLE completed_courses ADD INDEX IF NOT EXISTS idx_course (course_id);
ALTER TABLE completed_courses ADD INDEX IF NOT EXISTS idx_user (user_id);

-- Forum threads (for sidebar)
ALTER TABLE forum_threads ADD INDEX IF NOT EXISTS idx_created (created_at);

-- Events (for sidebar)
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_date (date);

-- Users
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_hidden (is_hidden);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role);

-- Program chapters
ALTER TABLE program_chapters ADD INDEX IF NOT EXISTS idx_program_sort (program_id, sort_index);

-- Steps and tasks
ALTER TABLE steps ADD INDEX IF NOT EXISTS idx_lesson (lesson_id);
ALTER TABLE tasks ADD INDEX IF NOT EXISTS idx_step (step_id);
ALTER TABLE tasks ADD INDEX IF NOT EXISTS idx_is_star (is_star);

-- Display indexes that were created
SHOW INDEX FROM courses WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM solutions WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM lessons WHERE Key_name LIKE 'idx_%';
