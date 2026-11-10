<?php

// Built-in guides for guided events (PRD C-05). Each step: title and one or two plain sentences.
return [
    'guide_checkin' => [
        'name' => 'Quarterly Check-in',
        'minutes' => 45,
        'steps' => [
            ['What is working', 'Each of you writes down what is going well and what isn\'t, before you compare notes.'],
            ['Missions and Numbers', 'Look at the person\'s Missions and their numbers for the quarter. Talk about the trend, not one bad week.'],
            ['Beliefs at work', 'Talk about where the Belief System shows up in their work. Use real examples instead of scores.'],
            ['Seat fit', 'Check the seat still fits: do they understand it, want it, and have the capacity to do it?'],
            ['One change each', 'Each of you names one thing you will do differently. The leader asks: "What should I do differently for you?"'],
            ['Commitments', 'Turn every commitment into a task with an owner and a due date before you leave.'],
        ],
    ],
    'guide_shoutout' => [
        'name' => 'Belief Shoutout',
        'minutes' => 10,
        'steps' => [
            ['Pick a belief', 'Choose one belief from the Belief System.'],
            ['Name the moment', 'Say who did it and exactly what they did, so everyone can picture it.'],
            ['Say why it matters', 'Explain in one sentence why that action is what the belief looks like here.'],
            ['Post it', 'Write it in the notes so it comes up in the Weekly Meeting headlines.'],
        ],
    ],
    'guide_reflection' => [
        'name' => 'Heart & Head Reflection',
        'minutes' => 90,
        'steps' => [
            ['Set the room', 'Block 60 to 120 minutes alone. Leave your phone in another room.'],
            ['Head', 'Write down the most important problem in your area, and what you have been avoiding.'],
            ['Heart', 'Ask yourself: did I give clear direction, give people what they need, let them own the work, act for the whole team, and hold people to their commitments with respect?'],
            ['One decision', 'End with one decision, written in the notes. Notes stay visible only to you.'],
        ],
    ],
    'guide_passion' => [
        'name' => 'Passion-Performance Matrix',
        'minutes' => 45,
        'steps' => [
            ['List the work', 'Write down everything you did in the last two weeks.'],
            ['Sort it', 'Put each item in one box: love it and great at it; like it and good at it; don\'t like it but good at it; don\'t like it and not good at it.'],
            ['Plan the bottom two', 'For the last two boxes, decide who could take each item over.'],
            ['Hand it off', 'Create a task for every handoff you agreed on.'],
        ],
    ],
    'quarterly_session' => [
        'name' => 'Quarterly session',
        'minutes' => 480,
        'steps' => [
            ['Check in', 'Each person shares their best news and what they expect from today.'],
            ['Close last quarter', 'Close every Mission from last quarter as done or not done.'],
            ['Metric trends', 'Review the 13-week trend of each team metric.'],
            ['Reread the Fundamentals', 'Read the Fundamentals out loud and fix anything that no longer fits.'],
            ['Set Missions', 'Set the new quarter\'s Missions, three to seven per team.'],
            ['Long-term problems', 'Work the long-term Problem Queue, most important first.'],
            ['Cascade', 'Agree on the message each leader takes back to their team.'],
        ],
    ],
    'annual_session' => [
        'name' => 'Annual session',
        'minutes' => 960,
        'steps' => [
            ['Day 1: review the year', 'Look at what happened against the Annual Priority, metric by metric.'],
            ['Day 1: update the plan', 'Update the 3-Year Snapshot and set the new Annual Priority.'],
            ['Day 2: first-quarter Missions', 'Set the Missions for the first quarter of the new year.'],
            ['Day 2: long-term problems', 'Work the long-term Problem Queue until time runs out.'],
        ],
    ],
];
