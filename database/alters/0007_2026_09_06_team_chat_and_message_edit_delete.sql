-- Internal Team Chat (super admin <-> each admin) + edit/delete support on
-- both customer-support chat and team chat messages.
-- Additive only. "already exists" style errors are treated as already-applied.

create table if not exists `admin_conversations` (
    `id` bigint unsigned not null auto_increment primary key,
    `admin_id` bigint unsigned not null,
    `created_at` timestamp null,
    `updated_at` timestamp null,
    unique key `admin_conversations_admin_id_unique` (`admin_id`),
    constraint `admin_conversations_admin_id_foreign` foreign key (`admin_id`) references `users` (`id`) on delete cascade
) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;

create table if not exists `admin_messages` (
    `id` bigint unsigned not null auto_increment primary key,
    `admin_conversation_id` bigint unsigned not null,
    `sender_id` bigint unsigned not null,
    `type` varchar(255) not null default 'text',
    `body` text null,
    `file_path` varchar(255) null,
    `file_name` varchar(255) null,
    `is_read` tinyint(1) not null default '0',
    `read_at` timestamp null,
    `created_at` timestamp null,
    `updated_at` timestamp null,
    key `admin_messages_admin_conversation_id_foreign` (`admin_conversation_id`),
    key `admin_messages_sender_id_foreign` (`sender_id`),
    constraint `admin_messages_admin_conversation_id_foreign` foreign key (`admin_conversation_id`) references `admin_conversations` (`id`) on delete cascade,
    constraint `admin_messages_sender_id_foreign` foreign key (`sender_id`) references `users` (`id`) on delete cascade
) default character set utf8mb4 collate 'utf8mb4_0900_ai_ci' engine = InnoDB;

alter table `chat_messages` add `edited_at` timestamp null after `body`;
alter table `chat_messages` add `is_deleted` tinyint(1) not null default '0' after `edited_at`;

alter table `admin_messages` add `edited_at` timestamp null after `body`;
alter table `admin_messages` add `is_deleted` tinyint(1) not null default '0' after `edited_at`;
