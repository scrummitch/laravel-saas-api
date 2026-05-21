<?php

namespace App\Models\Usage;

enum AggregationValue: string
{
    case Latest = 'LATEST';
    case Sum = 'SUM';
//    case Average = 'AVERAGE';
    case Count = 'COUNT';

}
//*  Aggregation    Description    Transcription
//*  COUNT    Count the number of times an incoming event occurs    COUNT(events.code)
//*  COUNT UNIQUE    Count the number of unique value of a defined property for incoming events    COUNT_DISTINCT(events.properties.property_name)
//*  LATEST    Get the latest value of a defined property for incoming events    LAST_VALUE(events.properties.property_name) OVER ([PARTITION BY events.timestamp])
// *  MAX    Get the maximum value of a defined property for incoming events    MAX(events.properties.property_name)
//*  SUM    Sum a defined property for incoming events    SUM(events.properties.property_name)
//*  WEIGHTED SUM    Sum a defined property for incoming events, prorated based on time used per period    SUM(events.properties.property_name) / (DATEDIFF(SECOND, timestamp.event_1, timestamp.event_2) + 1) * (DATEDIFF(SECOND, 'period_start', 'period_end') + 1)
