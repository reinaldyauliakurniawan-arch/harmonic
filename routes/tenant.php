<?php

use App\Http\Controllers\Org;
use Illuminate\Support\Facades\Route;

Route::get('/', [Org\HomeController::class, 'index'])->name('home');

Route::get('/settings', [Org\SettingsController::class, 'edit'])->name('settings.edit');
Route::put('/settings', [Org\SettingsController::class, 'update'])->name('settings.update');

Route::get('/people', [Org\PeopleController::class, 'index'])->name('people.index');
Route::post('/people/invite', [Org\PeopleController::class, 'invite'])->name('people.invite');
Route::post('/invitations/{invitation}/resend', [Org\PeopleController::class, 'resend'])->name('people.resend');
Route::delete('/invitations/{invitation}', [Org\PeopleController::class, 'revoke'])->name('people.revoke');
Route::post('/people/{membership}/admin', [Org\PeopleController::class, 'toggleAdmin'])->name('people.admin');
Route::post('/people/{membership}/reactivate', [Org\PeopleController::class, 'reactivate'])->name('people.reactivate');
Route::post('/people/{membership}/reset-link', [Org\PeopleController::class, 'resetLink'])->name('people.reset-link');

Route::get('/activity', [Org\ActivityController::class, 'index'])->name('activity.index');

Route::get('/handover', [Org\HandoverController::class, 'show'])->name('handover.show');
Route::post('/handover', [Org\HandoverController::class, 'store'])->name('handover.store');

Route::get('/formation', [Org\FormationController::class, 'index'])->name('formation.index');
Route::get('/formation/seats/create', [Org\FormationController::class, 'create'])->name('formation.create');
Route::post('/formation/seats', [Org\FormationController::class, 'store'])->name('formation.store');
Route::get('/formation/seats/{seat}/edit', [Org\FormationController::class, 'edit'])->name('formation.edit');
Route::put('/formation/seats/{seat}', [Org\FormationController::class, 'update'])->name('formation.update');
Route::post('/formation/seats/{seat}/holder', [Org\FormationController::class, 'holder'])->name('formation.holder');
Route::post('/formation/seats/{seat}/move', [Org\FormationController::class, 'move'])->name('formation.move');
Route::post('/formation/seats/{seat}/order', [Org\FormationController::class, 'order'])->name('formation.order');
Route::delete('/formation/seats/{seat}', [Org\FormationController::class, 'destroy'])->name('formation.destroy');

Route::get('/tasks', [Org\TaskController::class, 'index'])->name('tasks.index');
Route::post('/tasks', [Org\TaskController::class, 'store'])->name('tasks.store');
Route::get('/tasks/{task}', [Org\TaskController::class, 'show'])->name('tasks.show');
Route::put('/tasks/{task}', [Org\TaskController::class, 'update'])->name('tasks.update');
Route::post('/tasks/{task}/status', [Org\TaskController::class, 'status'])->name('tasks.status');
Route::post('/tasks/{task}/steps/{step}', [Org\TaskController::class, 'step'])->name('tasks.step');
Route::delete('/tasks/{task}', [Org\TaskController::class, 'destroy'])->name('tasks.destroy');

Route::get('/boards', [Org\BoardController::class, 'index'])->name('boards.index');
Route::post('/boards', [Org\BoardController::class, 'store'])->name('boards.store');
Route::get('/boards/{board}', [Org\BoardController::class, 'show'])->name('boards.show');
Route::post('/boards/{board}/archive', [Org\BoardController::class, 'archive'])->name('boards.archive');
Route::post('/boards/{board}/columns', [Org\BoardController::class, 'addColumn'])->name('boards.columns.store');
Route::put('/boards/{board}/columns/{column}', [Org\BoardController::class, 'updateColumn'])->name('boards.columns.update');
Route::delete('/boards/{board}/columns/{column}', [Org\BoardController::class, 'destroyColumn'])->name('boards.columns.destroy');
Route::post('/boards/{board}/tasks/{task}/move', [Org\BoardController::class, 'moveCard'])->name('boards.move');

Route::get('/calendar', [Org\CalendarController::class, 'index'])->name('calendar.index');
Route::post('/calendar/events', [Org\CalendarController::class, 'store'])->name('calendar.store');
Route::put('/calendar/events/{event}', [Org\CalendarController::class, 'update'])->name('calendar.update');
Route::delete('/calendar/events/{event}', [Org\CalendarController::class, 'destroy'])->name('calendar.destroy');
Route::patch('/calendar/events/{event}/notes', [Org\CalendarController::class, 'notes'])->name('calendar.notes');

Route::get('/metrics', [Org\MetricController::class, 'index'])->name('metrics.index');
Route::get('/metrics/create', [Org\MetricController::class, 'create'])->name('metrics.create');
Route::post('/metrics', [Org\MetricController::class, 'store'])->name('metrics.store');
Route::get('/metrics/{metric}', [Org\MetricController::class, 'show'])->name('metrics.show');
Route::put('/metrics/{metric}', [Org\MetricController::class, 'update'])->name('metrics.update');
Route::post('/metrics/{metric}/archive', [Org\MetricController::class, 'archive'])->name('metrics.archive');
Route::post('/metrics/order', [Org\MetricController::class, 'order'])->name('metrics.order');
Route::post('/metrics/{metric}/values', [Org\MetricController::class, 'value'])->name('metrics.value');

Route::get('/numbers', [Org\NumbersController::class, 'index'])->name('numbers.index');
Route::post('/numbers', [Org\NumbersController::class, 'store'])->name('numbers.store');

Route::get('/missions', [Org\MissionController::class, 'index'])->name('missions.index');
Route::get('/missions/create', [Org\MissionController::class, 'create'])->name('missions.create');
Route::post('/missions', [Org\MissionController::class, 'store'])->name('missions.store');
Route::get('/missions/{mission}', [Org\MissionController::class, 'show'])->name('missions.show');
Route::put('/missions/{mission}', [Org\MissionController::class, 'update'])->name('missions.update');
Route::post('/missions/{mission}/status', [Org\MissionController::class, 'status'])->name('missions.status');
Route::post('/missions/{mission}/close', [Org\MissionController::class, 'close'])->name('missions.close');
Route::delete('/missions/{mission}', [Org\MissionController::class, 'destroy'])->name('missions.destroy');
Route::post('/missions/{mission}/milestones', [Org\MissionController::class, 'addMilestone'])->name('missions.milestones.store');
Route::post('/missions/{mission}/milestones/{milestone}', [Org\MissionController::class, 'toggleMilestone'])->name('missions.milestones.toggle');
Route::delete('/missions/{mission}/milestones/{milestone}', [Org\MissionController::class, 'destroyMilestone'])->name('missions.milestones.destroy');

Route::get('/problems', [Org\ProblemController::class, 'index'])->name('problems.index');
Route::get('/problems/create', [Org\ProblemController::class, 'create'])->name('problems.create');
Route::post('/problems', [Org\ProblemController::class, 'store'])->name('problems.store');
Route::get('/problems/{problem}', [Org\ProblemController::class, 'show'])->name('problems.show');
Route::post('/problems/{problem}/maintain', [Org\ProblemController::class, 'maintain'])->name('problems.maintain');

Route::get('/solve', [Org\SolveController::class, 'index'])->name('solve.index');
Route::post('/problems/{problem}/solve', [Org\SolveController::class, 'start'])->name('solve.start');
Route::post('/problems/{problem}/solve/quick', [Org\SolveController::class, 'quick'])->name('solve.quick');
Route::post('/problems/{problem}/solve/step/{step}', [Org\SolveController::class, 'step'])->whereNumber('step')->name('solve.step');
Route::post('/problems/{problem}/solve/upgrade', [Org\SolveController::class, 'upgrade'])->name('solve.upgrade');
Route::post('/problems/{problem}/solve/evaluate', [Org\SolveController::class, 'evaluate'])->name('solve.evaluate');
Route::post('/problems/{problem}/solve/again', [Org\SolveController::class, 'raiseAgain'])->name('solve.again');

Route::get('/teams', [Org\TeamController::class, 'index'])->name('teams.index');
Route::get('/teams/{seat}', [Org\TeamController::class, 'show'])->name('teams.show');
Route::put('/teams/{seat}/cadence', [Org\TeamController::class, 'cadence'])->name('teams.cadence');

Route::get('/meetings', [Org\MeetingController::class, 'index'])->name('meetings.index');
Route::get('/meetings/run/{event}', [Org\MeetingController::class, 'run'])->name('meetings.run');
Route::post('/meetings/run/{event}', [Org\MeetingController::class, 'start'])->name('meetings.start');
Route::get('/meetings/{meeting}', [Org\MeetingController::class, 'show'])->name('meetings.show');
Route::get('/meetings/{meeting}/state', [Org\MeetingController::class, 'state'])->name('meetings.state');
Route::post('/meetings/{meeting}/segment', [Org\MeetingController::class, 'segment'])->name('meetings.segment');
Route::post('/meetings/{meeting}/attendance', [Org\MeetingController::class, 'attendance'])->name('meetings.attendance');
Route::post('/meetings/{meeting}/rate', [Org\MeetingController::class, 'rate'])->name('meetings.rate');
Route::post('/meetings/{meeting}/headlines', [Org\MeetingController::class, 'headline'])->name('meetings.headline');
Route::post('/meetings/{meeting}/drop', [Org\MeetingController::class, 'drop'])->name('meetings.drop');
Route::match(['put', 'patch'], '/meetings/{meeting}/notes', [Org\MeetingController::class, 'notes'])->name('meetings.notes');
Route::post('/meetings/{meeting}/end', [Org\MeetingController::class, 'end'])->name('meetings.end');

Route::get('/fundamentals', [Org\FundamentalsController::class, 'show'])->name('fundamentals.show');
Route::get('/fundamentals/edit', [Org\FundamentalsController::class, 'edit'])->name('fundamentals.edit');
Route::post('/fundamentals/draft', [Org\FundamentalsController::class, 'save'])->name('fundamentals.save');
Route::get('/fundamentals/history', [Org\FundamentalsController::class, 'history'])->name('fundamentals.history');
Route::post('/beliefs', [Org\FundamentalsController::class, 'beliefStore'])->name('beliefs.store');
Route::put('/beliefs/{belief}', [Org\FundamentalsController::class, 'beliefUpdate'])->name('beliefs.update');

Route::get('/alignment', [Org\AlignmentController::class, 'index'])->name('alignment.index');
Route::post('/alignment', [Org\AlignmentController::class, 'open'])->name('alignment.open');
Route::post('/alignment/{round}/answer', [Org\AlignmentController::class, 'answer'])->name('alignment.answer');
Route::post('/alignment/{round}/close', [Org\AlignmentController::class, 'close'])->name('alignment.close');
Route::post('/alignment/{round}/remind', [Org\AlignmentController::class, 'remind'])->name('alignment.remind');
Route::post('/alignment/{round}/problem', [Org\AlignmentController::class, 'commentToProblem'])->name('alignment.problem');

Route::get('/procedures', [Org\ProcedureController::class, 'index'])->name('procedures.index');
Route::get('/sync', [Org\ProcedureController::class, 'sync'])->name('sync.index');
Route::get('/procedures/create', [Org\ProcedureController::class, 'create'])->name('procedures.create');
Route::post('/procedures', [Org\ProcedureController::class, 'store'])->name('procedures.store');
Route::get('/procedures/{procedure}', [Org\ProcedureController::class, 'show'])->name('procedures.show');
Route::get('/procedures/{procedure}/edit', [Org\ProcedureController::class, 'edit'])->name('procedures.edit');
Route::put('/procedures/{procedure}', [Org\ProcedureController::class, 'update'])->name('procedures.update');
Route::post('/procedures/{procedure}/preview', [Org\ProcedureController::class, 'preview'])->name('procedures.preview');
Route::post('/procedures/{procedure}/acknowledge', [Org\ProcedureController::class, 'acknowledge'])->name('procedures.acknowledge');
Route::post('/procedures/{procedure}/audits', [Org\ProcedureController::class, 'audit'])->name('procedures.audit');
Route::post('/procedures/{procedure}/review', [Org\ProcedureController::class, 'review'])->name('procedures.review');
Route::post('/procedures/{procedure}/archive', [Org\ProcedureController::class, 'archive'])->name('procedures.archive');

Route::get('/evaluator', [Org\EvaluatorController::class, 'index'])->name('evaluator.index');
Route::get('/evaluator/create', [Org\EvaluatorController::class, 'create'])->name('evaluator.create');
Route::post('/evaluator', [Org\EvaluatorController::class, 'store'])->name('evaluator.store');
Route::get('/evaluator/people/{membership}', [Org\EvaluatorController::class, 'person'])->name('evaluator.person');
Route::get('/evaluator/{evaluation}/edit', [Org\EvaluatorController::class, 'edit'])->name('evaluator.edit');
Route::put('/evaluator/{evaluation}', [Org\EvaluatorController::class, 'update'])->name('evaluator.update');

Route::get('/diagnostic', [Org\DiagnosticController::class, 'index'])->name('diagnostic.index');
Route::post('/diagnostic', [Org\DiagnosticController::class, 'open'])->name('diagnostic.open');
Route::post('/diagnostic/{cycle}/answer', [Org\DiagnosticController::class, 'answer'])->name('diagnostic.answer');
Route::post('/diagnostic/{cycle}/problem', [Org\DiagnosticController::class, 'raise'])->name('diagnostic.problem');

Route::get('/assessment', [Org\AssessmentController::class, 'index'])->name('assessment.index');
Route::get('/assessment/setup', [Org\AssessmentController::class, 'setup'])->name('assessment.setup');
Route::post('/assessment/setup', [Org\AssessmentController::class, 'saveSetup'])->name('assessment.setup.save');
Route::post('/assessment/order', [Org\AssessmentController::class, 'order'])->name('assessment.order');
Route::post('/assessment/cycles', [Org\AssessmentController::class, 'storeCycle'])->name('assessment.cycles.store');
Route::get('/assessment/cycles/{cycle}', [Org\AssessmentController::class, 'cycle'])->name('assessment.cycle');
Route::post('/assessment/cycles/{cycle}/matrix', [Org\AssessmentController::class, 'matrix'])->name('assessment.matrix');
Route::post('/assessment/cycles/{cycle}/open', [Org\AssessmentController::class, 'openNow'])->name('assessment.open');
Route::delete('/assessment/cycles/{cycle}', [Org\AssessmentController::class, 'destroy'])->name('assessment.destroy');
Route::post('/assessment/cycles/{cycle}/assignments/{assignment}/assessors', [Org\AssessmentController::class, 'addAssessor'])->name('assessment.assessors.store');
Route::delete('/assessment/cycles/{cycle}/assessors/{assessor}', [Org\AssessmentController::class, 'removeAssessor'])->name('assessment.assessors.destroy');
Route::get('/assessment/answer/{assessor}', [Org\AssessmentController::class, 'answerForm'])->name('assessment.answer');
Route::post('/assessment/answer/{assessor}', [Org\AssessmentController::class, 'answer'])->name('assessment.answer.store');
Route::get('/assessment/people/{membership}', [Org\AssessmentController::class, 'person'])->name('assessment.person');
