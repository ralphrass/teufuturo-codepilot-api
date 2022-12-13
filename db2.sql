create table user_code(id SERIAL, moodle_id INTEGER, moodle_user VARCHAR(300), moodle_fullname VARCHAR(2000), code TEXT, event_time TIMESTAMP NOT NULL DEFAULT NOW());

create table content(id SERIAL, keyword VARCHAR(2000), topic VARCHAR(2000), link VARCHAR(2000));