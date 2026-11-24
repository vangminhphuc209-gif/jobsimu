-- Rollback for Cumulative Assessment Feedback & Academic Condition Mapping (v3.1.0)
-- Drops only the newly added tables without affecting any legacy or core tables.

DROP TABLE IF EXISTS `user_assessment_reflections`;
DROP TABLE IF EXISTS `user_academic_profiles`;
