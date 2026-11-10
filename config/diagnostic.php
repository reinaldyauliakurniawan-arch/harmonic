<?php

// Company Diagnostic: twenty statements, each rated 1 (weak) to 5 (strong); the sum of the twenty ratings is the score out of 100.
return [
    'sections' => [
        'direction' => 'Direction', 'measurements' => 'Measurements', 'team' => 'Team',
        'resolution' => 'Resolution', 'procedures' => 'Procedures', 'discipline' => 'Discipline',
    ],
    'scale' => [1 => '1 Weak', 2 => '2', 3 => '3', 4 => '4', 5 => '5 Strong'],
    'statements' => [
        'q1' => ['direction', 'Our direction is written down, has been explained to everyone, and everyone shares it.'],
        'q2' => ['direction', 'Our beliefs are clear, and we hire, review, reward, and let people go by them.'],
        'q3' => ['direction', 'Our Why & Way is clear, and we keep our people, systems, and procedures aligned with it.'],
        'q4' => ['direction', 'Our Long-Term Objective is clear, repeated often, and shared by everyone.'],
        'q5' => ['direction', 'We know exactly who our ideal customer is, and all our sales and marketing aim at them.'],
        'q6' => ['direction', 'Our three uniques are clear, and all our sales and marketing say them.'],
        'q7' => ['procedures', 'We serve customers through a proven process that has a name and a drawing, and every salesperson uses it.'],
        'q8' => ['team', 'Everyone here is a right fit: they suit our culture and live our beliefs.'],
        'q9' => ['team', 'Our Winning Formation shows every seat and its responsibilities, is complete, and is kept up to date.'],
        'q10' => ['team', 'Everyone is in the right seat: they understand it, want it, and can do it well.'],
        'q11' => ['resolution', 'Our leadership team is open and honest with each other and trusts each other deeply.'],
        'q12' => ['discipline', 'Everyone has one to seven priorities for the quarter and stays focused on them.'],
        'q13' => ['discipline', 'Everyone takes part in a regular weekly meeting.'],
        'q14' => ['discipline', 'Every weekly meeting is on the same day and time, follows the same agenda, and starts and ends on time.'],
        'q15' => ['resolution', 'Every team names, discusses, and solves problems for the long-term good of the company.'],
        'q16' => ['procedures', 'Our core procedures are written down, kept simple, and followed by everyone, so we get the results we want.'],
        'q17' => ['measurements', 'We hear from customers and staff regularly, so we always know how satisfied they are.'],
        'q18' => ['measurements', 'We track a set of metrics every week on one dashboard.'],
        'q19' => ['measurements', 'Everyone has at least one number they keep on track each week.'],
        'q20' => ['measurements', 'We have a budget and check it regularly, such as monthly or quarterly.'],
    ],
];
