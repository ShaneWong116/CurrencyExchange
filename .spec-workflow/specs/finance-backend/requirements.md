# Requirements Document

## Introduction
本后台系统为财务人员与管理员提供统一的管理入口，覆盖仪表盘、交易记录、支付渠道、账户管理、系统设置等模块，支持基于角色的权限控制与安全审计，保障数据安全与分级使用。

## Alignment with Product Vision
本功能与整体外汇/资金管理系统目标一致：为内部财务与运营提供高效、可审计、可配置的后台能力，提升资金流转透明度、运营管理效率与风控能力。

## Requirements

### Requirement 1：登录与安全
**User Story:** 作为后台用户（管理员或财务人员），我希望使用账号与密码安全登录，并基于角色获取访问令牌，以便按权限访问各模块。

#### Acceptance Criteria
1. WHEN 用户提交正确的账号与密码 THEN 系统 SHALL 返回 Access Token 与 Refresh Token。
2. IF 用户角色为管理员或财务 THEN 系统 SHALL 基于 RBAC 控制其模块与接口访问权限。
3. WHEN Token 过期 AND 用户使用 Refresh Token 刷新 THEN 系统 SHALL 返回新的 Access Token。
4. WHEN 管理员执行强制登出某账户 THEN 系统 SHALL 立即使该用户的有效会话与 Token 失效。
5. WHEN 用户登录/登出/鉴权失败 THEN 系统 SHALL 在审计日志中记录事件（时间、账号、IP、结果）。

### Requirement 2：仪表盘
**User Story:** 作为财务人员/管理员，我希望在仪表盘快速查看当日渠道汇总与关键趋势图，以便实时掌握收入、支出与资金分布情况。

#### Acceptance Criteria
1. WHEN 打开仪表盘 THEN 系统 SHALL 展示“今日支付渠道汇总表格”，含：支付渠道、交易笔数、总入账金额、总出账金额、渠道余额。
2. WHEN 选择时间维度（今日/本月） THEN 系统 SHALL 展示收入/支出趋势图。
3. WHEN 加载仪表盘 THEN 系统 SHALL 展示人民币与港币资金分布（饼图）。
4. WHEN 加载仪表盘 THEN 系统 SHALL 展示各支付渠道交易量对比（柱状图）。
5. WHEN 加载仪表盘 THEN 系统 SHALL 展示账户余额变化趋势图。

### Requirement 3：交易记录
**User Story:** 作为财务人员，我希望查询与导出交易记录；作为管理员，我还希望能编辑交易记录，以修正明显错误。

#### Acceptance Criteria
1. WHEN 查看交易列表 THEN 系统 SHALL 展示列：交易号、人员、地点、交易类型（入/出）、货币类型、金额、支付渠道、状态、备注、创建时间。
2. WHEN 用户拖动列顺序或隐藏/显示列 THEN 系统 SHALL 应用并持久化列配置到当前用户偏好。
3. WHEN 用户按时间范围（今日/本周/本月/自定义）、入账/出账、人民币/港币筛选 THEN 系统 SHALL 返回过滤后的分页结果。
4. IF 当前用户为管理员 AND 交易处于可编辑状态 THEN 系统 SHALL 允许编辑交易记录并记录审计日志。
5. WHEN 用户执行导出（月结/年结，Excel/PDF） THEN 系统 SHALL 异步生成报表并在完成后可下载。

### Requirement 4：支付渠道管理
**User Story:** 作为管理员，我希望新增/修改/停用支付渠道并查看统计；作为财务人员，我希望只读查看渠道信息与统计。

#### Acceptance Criteria
1. WHEN 管理员新增或修改渠道 THEN 系统 SHALL 支持字段：渠道名称、标签（线上/线下/第三方等）、分类（银行/电子钱包/现金等）、启用状态。
2. WHEN 展示渠道列表 THEN 系统 SHALL 显示累计交易次数与渠道余额等统计信息。
3. IF 当前用户为财务人员 THEN 系统 SHALL 仅提供查看权限，不允许更改。
4. WHEN 管理员停用渠道 THEN 系统 SHALL 阻止新交易使用该渠道并保留历史数据可查。

### Requirement 5：账户管理
**User Story:** 作为管理员，我希望创建、编辑与禁用后台账户，并分配角色，以确保最小权限原则。

#### Acceptance Criteria
1. WHEN 管理员新建账号 THEN 系统 SHALL 录入账号、初始角色与状态。
2. WHEN 管理员编辑账号 THEN 系统 SHALL 支持修改密码、角色与状态，并记录审计日志。
3. WHEN 管理员禁用/启用账号 THEN 系统 SHALL 立即生效并影响其登录与访问权限。
4. WHEN 展示账号列表 THEN 系统 SHALL 显示账号、角色、状态、最后登录时间。

### Requirement 6：系统设置
**User Story:** 作为管理员，我希望配置系统全局参数（Token 过期、自动登出、图片存储、汇率精度、日志周期），以满足运营与合规要求。

#### Acceptance Criteria
1. WHEN 管理员打开系统设置 THEN 系统 SHALL 展示参数项：Access/Refresh Token 过期时间、自动登出时间、图片大小与压缩比例、汇率精度（小数位数）、日志保存周期。
2. IF 非管理员访问系统设置 THEN 系统 SHALL 拒绝并记录审计日志。
3. WHEN 管理员修改任一参数 THEN 系统 SHALL 校验、保存并对后续请求生效（必要时提示需重新登录或后台重载）。

### Requirement 7：审计与安全合规
**User Story:** 作为安全/合规负责人，我希望系统具备全站 HTTPS、基于 RBAC 的权限模型与操作日志审计，以便追踪关键操作并满足合规。

#### Acceptance Criteria
1. WHEN 系统部署 THEN 系统 SHALL 强制启用 HTTPS（包括后台管理域名）。
2. WHEN 用户访问任一受保护资源 THEN 系统 SHALL 基于 RBAC 判断权限并拒绝越权访问。
3. WHEN 发生关键操作（登录、登出、编辑交易、修改系统参数、账号变更、导出报表） THEN 系统 SHALL 记录审计日志（操作者、时间、来源、动作、对象、结果）。

## Non-Functional Requirements

### Code Architecture and Modularity
- Single Responsibility Principle：每个文件应有单一明确职责。
- Modular Design：组件、服务与工具模块化、可复用。
- Dependency Management：模块间依赖最小化，边界清晰。
- Clear Interfaces：层与组件之间定义清晰契约与 DTO。

### Performance
- 交易数据采用分页加载，默认按时间倒序，保证列表首屏 < 2s。
- 报表导出支持大数据量，采用异步导出并在后台完成后可下载。

### Security
- RBAC 权限控制覆盖所有后台模块与接口。
- 全站 HTTPS 与安全头配置，敏感数据入库/出库脱敏（如适用）。
- Token 有效期与刷新机制可配置，支持管理员强制登出。
- 全量审计日志可检索、可归档，保留周期可配置。

### Reliability
- 审计日志与系统参数变更应具备持久化与可追溯性。
- 导出任务失败可重试，且不影响在线查询使用。

### Usability
- 列显示/隐藏与顺序配置持久化到用户偏好。
- 仪表盘图表直观，支持基础时间维度切换（今日/本周/本月）。
