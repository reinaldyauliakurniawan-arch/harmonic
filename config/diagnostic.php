<?php

// Company Diagnostic statements (PRD section 17). Keys d1–x4; four per section.
return [
    'sections' => [
        'direction' => 'Direction', 'measurements' => 'Measurements', 'team' => 'Team',
        'resolution' => 'Resolution', 'procedures' => 'Procedures', 'discipline' => 'Discipline',
    ],
    'scale' => [1 => 'Strongly disagree', 2 => 'Disagree', 3 => 'Neutral', 4 => 'Agree', 5 => 'Strongly agree'],
    'statements' => [
        'd1' => ['direction', 'Everyone here could explain where we\'re going in the next three years.'],
        'd2' => ['direction', 'Our beliefs guide who we hire, reward, and let go.'],
        'd3' => ['direction', 'We turn down opportunities that don\'t fit what we do best.'],
        'd4' => ['direction', 'This year\'s priorities are written down and known by everyone.'],
        'm1' => ['measurements', 'We review a small set of numbers every week.'],
        'm2' => ['measurements', 'Every person in a seat owns at least one number.'],
        'm3' => ['measurements', 'Our numbers warn us about problems before customers notice.'],
        'm4' => ['measurements', 'The goal for each number is clear and agreed.'],
        't1' => ['team', 'Everyone knows who is accountable for what.'],
        't2' => ['team', 'People in key seats live our beliefs.'],
        't3' => ['team', 'People in key seats understand, want, and can do their roles.'],
        't4' => ['team', 'Leaders hold regular one-on-one check-ins with their people.'],
        'r1' => ['resolution', 'We raise problems openly instead of working around them.'],
        'r2' => ['resolution', 'When we solve a problem, it stays solved.'],
        'r3' => ['resolution', 'Our leaders say what they really think in meetings.'],
        'r4' => ['resolution', 'Every open problem has an owner.'],
        'p1' => ['procedures', 'Our core procedures are written down.'],
        'p2' => ['procedures', 'People follow our core procedures consistently.'],
        'p3' => ['procedures', 'We review and improve our procedures on a schedule.'],
        'p4' => ['procedures', 'A new hire could learn how we work from our documentation.'],
        'x1' => ['discipline', 'Each team has three to seven clear priorities this quarter.'],
        'x2' => ['discipline', 'Our weekly meetings start on time, follow one agenda, and end on time.'],
        'x3' => ['discipline', 'Most commitments made in meetings are done by their due date.'],
        'x4' => ['discipline', 'Each person knows how their work connects to company priorities.'],
    ],
];
