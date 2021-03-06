<?php

namespace Ilios\AuthenticationBundle\Classes;

/**
 * Constants-interface that defines all capabilities in the system.
 */
interface Capabilities
{
    /** @var string */
    const CAN_UPDATE_ALL_COURSES = 'canUpdateAllCourses';
    /** @var string */
    const CAN_DELETE_ALL_COURSES = 'canDeleteAllCourses';
    /** @var string */
    const CAN_LOCK_ALL_COURSES = 'canLockAllCourses';
    /** @var string */
    const CAN_ARCHIVE_ALL_COURSES = 'canArchiveAllCourses';
    /** @var string */
    const CAN_UNLOCK_ALL_COURSES = 'canUnlockAllCourses';
    /** @var string */
    const CAN_CREATE_COURSES = 'canCreateCourses';
    /** @var string */
    const CAN_UPDATE_THEIR_COURSES = 'canUpdateTheirCourses';
    /** @var string */
    const CAN_DELETE_THEIR_COURSES = 'canDeleteTheirCourses';
    /** @var string */
    const CAN_LOCK_THEIR_COURSES = 'canLockTheirCourses';
    /** @var string */
    const CAN_ARCHIVE_THEIR_COURSES = 'canArchiveTheirCourses';
    /** @var string */
    const CAN_UNLOCK_THEIR_COURSES = 'canUnlockTheirCourses';
    /** @var string */
    const CAN_UPDATE_ALL_SESSIONS = 'canUpdateAllSessions';
    /** @var string */
    const CAN_DELETE_ALL_SESSIONS = 'canDeleteAllSessions';
    /** @var string */
    const CAN_CREATE_SESSIONS = 'canCreateSessions';
    /** @var string */
    const CAN_UPDATE_THEIR_SESSIONS = 'canUpdateTheirSessions';
    /** @var string */
    const CAN_DELETE_THEIR_SESSIONS = 'canDeleteTheirSessions';
    /** @var string */
    const CAN_UPDATE_SESSION_TYPES = 'canUpdateSessionTypes';
    /** @var string */
    const CAN_DELETE_SESSION_TYPES = 'canDeleteSessionTypes';
    /** @var string */
    const CAN_CREATE_SESSION_TYPES = 'canCreateSessionTypes';
    /** @var string */
    const CAN_UPDATE_DEPARTMENTS = 'canUpdateDepartments';
    /** @var string */
    const CAN_DELETE_DEPARTMENTS = 'canDeleteDepartments';
    /** @var string */
    const CAN_CREATE_DEPARTMENTS = 'canCreateDepartments';
    /** @var string */
    const CAN_UPDATE_ALL_PROGRAMS = 'canUpdateAllPrograms';
    /** @var string */
    const CAN_DELETE_ALL_PROGRAMS = 'canDeleteAllPrograms';
    /** @var string */
    const CAN_CREATE_PROGRAMS = 'canCreatePrograms';
    /** @var string */
    const CAN_UPDATE_THEIR_PROGRAMS = 'canUpdateTheirPrograms';
    /** @var string */
    const CAN_DELETE_THEIR_PROGRAMS = 'canDeleteTheirPrograms';
    /** @var string */
    const CAN_UPDATE_ALL_PROGRAM_YEARS = 'canUpdateAllProgramYears';
    /** @var string */
    const CAN_DELETE_ALL_PROGRAM_YEARS = 'canDeleteAllProgramYears';
    /** @var string */
    const CAN_UNLOCK_ALL_PROGRAM_YEARS = 'canUnlockAllProgramYears';
    /** @var string */
    const CAN_LOCK_ALL_PROGRAM_YEARS = 'canLockAllProgramYears';
    /** @var string */
    const CAN_ARCHIVE_ALL_PROGRAM_YEARS = 'canArchiveAllProgramYears';
    /** @var string */
    const CAN_CREATE_PROGRAM_YEARS = 'canCreateProgramYears';
    /** @var string */
    const CAN_UPDATE_THEIR_PROGRAM_YEARS = 'canUpdateTheirProgramYears';
    /** @var string */
    const CAN_DELETE_THEIR_PROGRAM_YEARS = 'canDeleteTheirProgramYears';
    /** @var string */
    const CAN_LOCK_THEIR_PROGRAM_YEARS = 'canLockTheirProgramYears';
    /** @var string */
    const CAN_ARCHIVE_THEIR_PROGRAM_YEARS = 'canArchiveTheirProgramYears';
    /** @var string */
    const CAN_UNLOCK_THEIR_PROGRAM_YEARS = 'canUnlockTheirProgramYears';
    /** @var string */
    const CAN_UPDATE_SCHOOL_CONFIGS = 'canUpdateSchoolConfigs';
    /** @var string */
    const CAN_DELETE_SCHOOL_CONFIGS = 'canDeleteSchoolConfigs';
    /** @var string */
    const CAN_CREATE_SCHOOL_CONFIGS = 'canCreateSchoolConfigs';
    /** @var string */
    const CAN_UPDATE_SCHOOLS = 'canUpdateSchools';
    /** @var string */
    const CAN_UPDATE_COMPETENCIES = 'canUpdateCompetencies';
    /** @var string */
    const CAN_DELETE_COMPETENCIES = 'canDeleteCompetencies';
    /** @var string */
    const CAN_CREATE_COMPETENCIES = 'canCreateCompetencies';
    /** @var string */
    const CAN_UPDATE_VOCABULARIES = 'canUpdateVocabularies';
    /** @var string */
    const CAN_DELETE_VOCABULARIES = 'canDeleteVocabularies';
    /** @var string */
    const CAN_CREATE_VOCABULARIES = 'canCreateVocabularies';
    /** @var string */
    const CAN_UPDATE_TERMS = 'canUpdateTerms';
    /** @var string */
    const CAN_DELETE_TERMS = 'canDeleteTerms';
    /** @var string */
    const CAN_CREATE_TERMS = 'canCreateTerms';
    /** @var string */
    const CAN_UPDATE_INSTRUCTOR_GROUPS = 'canUpdateInstructorGroups';
    /** @var string */
    const CAN_DELETE_INSTRUCTOR_GROUPS = 'canDeleteInstructorGroups';
    /** @var string */
    const CAN_CREATE_INSTRUCTOR_GROUPS = 'canCreateInstructorGroups';
    /** @var string */
    const CAN_UPDATE_ALL_CURRICULUM_INVENTORY_REPORTS = 'canUpdateAllCurriculumInventoryReports';
    /** @var string */
    const CAN_DELETE_ALL_CURRICULUM_INVENTORY_REPORTS = 'canDeleteAllCurriculumInventoryReports';
    /** @var string */
    const CAN_CREATE_CURRICULUM_INVENTORY_REPORTS = 'canCreateCurriculumInventoryReports';
    /** @var string */
    const CAN_UPDATE_THEIR_CURRICULUM_INVENTORY_REPORTS = 'canUpdateTheirCurriculumInventoryReports';
    /** @var string */
    const CAN_DELETE_THEIR_CURRICULUM_INVENTORY_REPORTS = 'canDeleteTheirCurriculumInventoryReports';
    /** @var string */
    const CAN_CREATE_CURRICULUM_INVENTORY_INSTITUTIONS = 'canCreateCurriculumInventoryInstitutions';
    /** @var string */
    const CAN_UPDATE_CURRICULUM_INVENTORY_INSTITUTIONS = 'canUpdateCurriculumInventoryInstitutions';
    /** @var string */
    const CAN_DELETE_CURRICULUM_INVENTORY_INSTITUTIONS = 'canDeleteCurriculumInventoryInstitutions';
    /** @var string */
    const CAN_CREATE_LEARNER_GROUPS = 'canCreateLearnerGroups';
    /** @var string */
    const CAN_UPDATE_LEARNER_GROUPS = 'canUpdateLearnerGroups';
    /** @var string */
    const CAN_DELETE_LEARNER_GROUPS = 'canDeleteLearnerGroups';
    /** @var string */
    const CAN_CREATE_USERS = 'canCreateUser';
    /** @var string */
    const CAN_UPDATE_USERS = 'canUpdateUser';
    /** @var string */
    const CAN_DELETE_USERS = 'canDeleteUser';
}
