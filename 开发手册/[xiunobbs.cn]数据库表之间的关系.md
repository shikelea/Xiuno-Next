erDiagram
    bbs_user ||--o{ bbs_thread : "uid 发帖"
    bbs_user ||--o{ bbs_post : "uid 回帖"
    bbs_user ||--o{ bbs_attach : "uid 上传附件"
    bbs_user ||--o{ bbs_mythread : "uid 我的主题"
    bbs_user ||--o{ bbs_mypost : "uid 我的回帖"
    bbs_user ||--o{ bbs_session : "uid 会话"
    bbs_user ||--o{ bbs_modlog : "uid 版主操作"
    bbs_user }o--|| bbs_group : "gid 用户组"

    bbs_group ||--o{ bbs_forum_access : "gid 访问权限"

    bbs_forum ||--o{ bbs_thread : "fid 所属版块"
    bbs_forum ||--o{ bbs_forum_access : "fid 访问规则"
    bbs_forum ||--o{ bbs_thread_top : "fid 版块置顶"
    bbs_forum ||--o{ bbs_session : "fid 当前版块"

    bbs_thread ||--|{ bbs_post : "tid 主题帖子"
    bbs_thread ||--o{ bbs_attach : "tid 主题附件"
    bbs_thread ||--o{ bbs_thread_top : "tid 置顶记录"
    bbs_thread ||--o{ bbs_mythread : "tid 我的主题"
    bbs_thread ||--o{ bbs_mypost : "tid 我的回帖"
    bbs_thread ||--o{ bbs_modlog : "tid 操作日志"
    bbs_thread }o--|| bbs_post : "firstpid 首帖"
    bbs_thread }o--|| bbs_post : "lastpid 最后回复"

    bbs_post ||--o{ bbs_attach : "pid 帖子附件"
    bbs_post ||--o{ bbs_post : "quotepid 引用帖子"
    bbs_post ||--o{ bbs_mypost : "pid 我的回帖"
    bbs_post ||--o{ bbs_modlog : "pid 操作日志"

    bbs_session ||--|| bbs_session_data : "sid 会话数据"

    bbs_user {
        int uid PK "用户编号"
        int gid FK "用户组编号"
        string email "邮箱"
        string username "用户名"
        string password "密码"
        string salt "密码混杂"
        int threads "发帖数"
        int posts "回帖数"
        int create_date "创建时间"
        int login_date "登录时间"
    }

    bbs_group {
        int gid PK "用户组编号"
        string name "用户组名称"
        int creditsfrom "积分从"
        int creditsto "积分到"
        int allowread "允许访问"
        int allowthread "允许发主题"
        int allowpost "允许回帖"
    }

    bbs_forum {
        int fid PK "版块编号"
        string name "版块名称"
        int rank "显示排序"
        int threads "主题数"
        int todayposts "今日发帖"
        string moduids "版主uid列表"
        string brief "版块简介"
    }

    bbs_forum_access {
        int fid PK,FK "版块编号"
        int gid PK,FK "用户组编号"
        int allowread "允许查看"
        int allowthread "允许发主题"
        int allowpost "允许回复"
        int allowattach "允许上传附件"
        int allowdown "允许下载附件"
    }

    bbs_thread {
        int fid FK "版块编号"
        int tid PK "主题编号"
        int top "置顶级别"
        int uid FK "用户编号"
        string subject "主题"
        int create_date "发帖时间"
        int last_date "最后回复时间"
        int views "查看次数"
        int posts "回帖数"
        int firstpid FK "首帖编号"
        int lastuid FK "最后回复用户"
        int lastpid FK "最后回复编号"
    }

    bbs_thread_top {
        int fid FK "版块编号"
        int tid PK,FK "主题编号"
        int top "置顶级别"
    }

    bbs_post {
        int tid FK "主题编号"
        int pid PK "帖子编号"
        int uid FK "用户编号"
        int isfirst "是否首帖"
        int create_date "发帖时间"
        int quotepid FK "引用帖子"
        string message "内容"
        string message_fmt "格式化内容"
    }

    bbs_attach {
        int aid PK "附件编号"
        int tid FK "主题编号"
        int pid FK "帖子编号"
        int uid FK "用户编号"
        int filesize "文件大小"
        string filename "文件名"
        string filetype "文件类型"
        int create_date "上传时间"
    }

    bbs_mythread {
        int uid PK,FK "用户编号"
        int tid PK,FK "主题编号"
    }

    bbs_mypost {
        int uid PK,FK "用户编号"
        int tid FK "主题编号"
        int pid PK,FK "帖子编号"
    }

    bbs_session {
        string sid PK "会话ID"
        int uid FK "用户编号"
        int fid FK "版块编号"
        string url "当前URL"
        int ip "用户IP"
        int last_date "最后活动时间"
    }

    bbs_session_data {
        string sid PK,FK "会话ID"
        int last_date "最后活动时间"
        text data "会话数据"
    }

    bbs_modlog {
        int logid PK "日志编号"
        int uid FK "版主编号"
        int tid FK "主题编号"
        int pid FK "帖子编号"
        string subject "主题"
        string comment "版主评价"
        int create_date "操作时间"
        string action "操作类型"
    }

    bbs_kv {
        string k PK "键"
        text v "值"
        int expiry "过期时间"
    }

    bbs_cache {
        string k PK "键"
        text v "值"
        int expiry "过期时间"
    }

    bbs_queue {
        int queueid "队列ID"
        int v "队列数据"
        int expiry "过期时间"
    }

    bbs_table_day {
        int year PK "年"
        int month PK "月"
        int day PK "日"
        int create_date "时间戳"
        string table "表名"
        int maxid "最大ID"
        int count "总数"
    }
