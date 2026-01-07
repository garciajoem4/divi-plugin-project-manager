import React, { Component, useState } from 'react';
import './style.css';

// ==================== MODAL COMPONENTS ====================

const ProjectModal = ({ isEditing, editingProject, onSubmit, onClose }) => {
	const [formData, setFormData] = useState({
		name: editingProject?.name || '',
		description: editingProject?.description || '',
		color: editingProject?.color || '#3b82f6'
	});
	
	const handleSubmit = (e) => {
		e.preventDefault();
		onSubmit(formData);
	};
	
	return (
		<div className="pm-modal-overlay" onClick={onClose}>
			<div className="pm-modal" onClick={e => e.stopPropagation()}>
				<div className="pm-modal-header">
					<h3>{isEditing ? 'Edit Project' : 'New Project'}</h3>
					<button className="pm-modal-close" onClick={onClose}>×</button>
				</div>
				<form onSubmit={handleSubmit}>
					<div className="pm-modal-body">
						<div className="pm-form-group">
							<label>Project Name *</label>
							<input
								type="text"
								value={formData.name}
								onChange={(e) => setFormData({...formData, name: e.target.value})}
								placeholder="Enter project name"
								required
							/>
						</div>
						<div className="pm-form-group">
							<label>Description</label>
							<textarea
								value={formData.description}
								onChange={(e) => setFormData({...formData, description: e.target.value})}
								placeholder="Project description (optional)"
								rows={3}
							/>
						</div>
						<div className="pm-form-group">
							<label>Color</label>
							<div className="pm-color-picker">
								{['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'].map(color => (
									<button
										key={color}
										type="button"
										className={`pm-color-option ${formData.color === color ? 'selected' : ''}`}
										style={{ backgroundColor: color }}
										onClick={() => setFormData({...formData, color})}
									/>
								))}
							</div>
						</div>
					</div>
					<div className="pm-modal-footer">
						<button type="button" className="pm-btn pm-btn-ghost" onClick={onClose}>
							Cancel
						</button>
						<button type="submit" className="pm-btn pm-btn-primary">
							{isEditing ? 'Save Changes' : 'Create Project'}
						</button>
					</div>
				</form>
			</div>
		</div>
	);
};

const TaskModal = ({ editingTask, statuses, users, priorityColors, onSubmit, onDelete, onClose, onDailyTasksChange }) => {
	const isEditing = !!editingTask?.id && !String(editingTask.id).startsWith('new_');
	const defaultStatus = statuses.length > 0 ? statuses[0].id : '';
	const priorities = Object.keys(priorityColors);
	
	const [formData, setFormData] = useState({
		title: editingTask?.title || '',
		description: editingTask?.description || '',
		priority: editingTask?.priority || 'Medium',
		due_date: editingTask?.due_date || '',
		assignee_id: editingTask?.assignee_id || '',
		status_id: editingTask?.status_id || defaultStatus
	});
	
	// Sort daily entries by created_at in ascending order
	const sortDailyEntries = (entries) => {
		return [...entries].sort((a, b) => {
			const dateA = new Date(a.created_at || 0);
			const dateB = new Date(b.created_at || 0);
			return dateA - dateB;
		});
	};
	
	const [dailyEntries, setDailyEntries] = useState(sortDailyEntries(editingTask?.dailyEntries || []));
	const [deletedEntryIds, setDeletedEntryIds] = useState([]);
	const [expandedEntry, setExpandedEntry] = useState(null);
	const [newEntry, setNewEntry] = useState({ description: '', end_of_day_report: '', start_time: '', end_time: '' });
	
	const handleSubmit = (e) => {
		e.preventDefault();
		onSubmit(formData, isEditing);
	};
	
	const toggleEntryExpansion = (index) => {
		setExpandedEntry(prev => prev === index ? null : index);
	};
	
	const calculateHours = (startTime, endTime) => {
		if (!startTime || !endTime) return '0h';
		const start = new Date(`2000-01-01 ${startTime}`);
		let end = new Date(`2000-01-01 ${endTime}`);
		
		// If end time is before start time, assume it's the next day
		if (end <= start) {
			end = new Date(`2000-01-02 ${endTime}`);
		}
		
		const diff = (end - start) / (1000 * 60 * 60);
		return diff > 0 ? `${diff.toFixed(1)}h` : '0h';
	};
	
	const formatEntryDate = (createdAt) => {
		if (!createdAt) return new Date().toLocaleDateString();
		const date = new Date(createdAt);
		return date.toLocaleDateString();
	};
	
	const addDailyEntry = () => {
		const entry = { ...newEntry, tempId: Date.now(), isNew: true, created_at: new Date().toISOString() };
		const updated = sortDailyEntries([...dailyEntries, entry]);
		setDailyEntries(updated);
		setNewEntry({ description: '', end_of_day_report: '', start_time: '', end_time: '' });
		if (onDailyTasksChange) onDailyTasksChange(editingTask.id, updated, deletedEntryIds);
	};
	
	const removeDailyEntry = (index) => {
		const entryToRemove = dailyEntries[index];
		
		// Create a title for the confirmation prompt
		const entryDate = formatEntryDate(entryToRemove.created_at);
		const entryTime = entryToRemove.start_time && entryToRemove.end_time 
			? `${entryToRemove.start_time} - ${entryToRemove.end_time}` 
			: 'No time set';
		const confirmTitle = `${entryDate} (${entryTime})`;
		
		// Show confirmation dialog
		if (!window.confirm(`Are you sure you want to delete "${confirmTitle}"?`)) {
			return;
		}
		
		const updated = sortDailyEntries(dailyEntries.filter((_, i) => i !== index));
		setDailyEntries(updated);
		
		// Track server-side entries for deletion
		if (entryToRemove.id && !entryToRemove.isNew) {
			const updatedDeleted = [...deletedEntryIds, entryToRemove.id];
			setDeletedEntryIds(updatedDeleted);
			if (onDailyTasksChange) onDailyTasksChange(editingTask.id, updated, updatedDeleted);
		} else {
			if (onDailyTasksChange) onDailyTasksChange(editingTask.id, updated, deletedEntryIds);
		}
	};
	
	const updateDailyEntry = (index, field, value) => {
		const updated = sortDailyEntries(dailyEntries.map((entry, i) => 
			i === index ? { ...entry, [field]: value, modified: true } : entry
		));
		setDailyEntries(updated);
		if (onDailyTasksChange) onDailyTasksChange(editingTask.id, updated, deletedEntryIds);
	};
	
	return (
		<div className="pm-modal-overlay" onClick={onClose}>
			<div className="pm-modal pm-modal-lg" onClick={e => e.stopPropagation()}>
				<div className="pm-modal-header">
					<h3>{isEditing ? 'Edit Task' : 'New Task'}</h3>
					<button className="pm-modal-close" onClick={onClose}>×</button>
				</div>
				<form onSubmit={handleSubmit}>
					<div className="pm-modal-body">
						<div className="pm-form-group">
							<label>Task Title *</label>
							<input
								type="text"
								value={formData.title}
								onChange={(e) => setFormData({...formData, title: e.target.value})}
								placeholder="What needs to be done?"
								required
							/>
						</div>
						
						<div className="pm-form-group">
							<label>Description</label>
							<textarea
								value={formData.description}
								onChange={(e) => setFormData({...formData, description: e.target.value})}
								placeholder="Add more details..."
								rows={4}
							/>
						</div>
						
						<div className="pm-form-row">
							<div className="pm-form-group">
								<label>Status</label>
								<select
									value={formData.status_id}
									onChange={(e) => setFormData({...formData, status_id: e.target.value})}
								>
									{statuses.map(status => (
										<option key={status.id} value={status.id}>
											{status.name}
										</option>
									))}
								</select>
							</div>
							
							<div className="pm-form-group">
								<label>Priority</label>
								<select
									value={formData.priority}
									onChange={(e) => setFormData({...formData, priority: e.target.value})}
								>
									{priorities.map(priority => (
										<option key={priority} value={priority}>
											{priority}
										</option>
									))}
								</select>
							</div>
						</div>
						
						<div className="pm-form-row">
							<div className="pm-form-group">
								<label>Due Date</label>
								<input
									type="date"
									value={formData.due_date}
									onChange={(e) => setFormData({...formData, due_date: e.target.value})}
								/>
							</div>
							
							<div className="pm-form-group">
								<label>Assignee</label>
								<select
									value={formData.assignee_id}
									onChange={(e) => setFormData({...formData, assignee_id: e.target.value})}
								>
									<option value="">Unassigned</option>
									{users.map(user => (
										<option key={user.id} value={user.id}>
											{user.name}
										</option>
									))}
								</select>
							</div>
						</div>
						
						{/* Daily Task Entries Section */}
						{isEditing && (
							<div className="pm-form-group pm-daily-tasks-section">
								<label>Daily Task Schedule</label>
								<div className="pm-daily-tasks-list">
									{dailyEntries.map((entry, index) => {
										const isExpanded = expandedEntry === index;
										const hours = calculateHours(entry.start_time, entry.end_time);
										const dateStr = formatEntryDate(entry.created_at);
										const timeRange = entry.start_time && entry.end_time 
											? `${entry.start_time} - ${entry.end_time}` 
											: 'No time set';
										
										return (
											<div key={index} className="pm-daily-task-accordion">
												<div 
													className="pm-daily-task-accordion-header"
													onClick={() => toggleEntryExpansion(index)}
												>
													<div className="pm-daily-task-accordion-title">
														<span className="pm-accordion-icon">{isExpanded ? '▼' : '▶'}</span>
														<span className="pm-accordion-date">{dateStr}</span>
														<span className="pm-accordion-time">{timeRange}</span>
														<span className="pm-accordion-hours">{hours}</span>
													</div>
													<button
														type="button"
														className="pm-btn-icon pm-btn-danger pm-daily-task-remove"
														onClick={(e) => {
															e.stopPropagation();
															removeDailyEntry(index);
														}}
														title="Remove entry"
													>
														×
													</button>
												</div>
												{isExpanded && (
													<div className="pm-daily-task-accordion-content">
														<div className="pm-daily-task-accordion-content-inner">
															<div className="pm-form-group">
																<label>Task Description</label>
																<textarea
																	value={entry.description}
																	onChange={(e) => updateDailyEntry(index, 'description', e.target.value)}
																	placeholder="Task description for the day..."
																	rows={2}
																	className="pm-daily-task-desc"
																/>
															</div>
															<div className="pm-form-group">
																<label>End of Day Report</label>
																<textarea
																	value={entry.end_of_day_report || ''}
																	onChange={(e) => updateDailyEntry(index, 'end_of_day_report', e.target.value)}
																	placeholder="Summary of what was accomplished..."
																	rows={3}
																	className="pm-daily-task-report"
																/>
															</div>
															<div className="pm-daily-task-times">
																<div className="pm-form-group">
																	<label>Start Time</label>
																	<input
																		type="time"
																		value={entry.start_time}
																		onChange={(e) => updateDailyEntry(index, 'start_time', e.target.value)}
																		className="pm-time-input"
																	/>
																</div>
																<div className="pm-form-group">
																	<label>End Time</label>
																	<input
																		type="time"
																		value={entry.end_time}
																		onChange={(e) => updateDailyEntry(index, 'end_time', e.target.value)}
																		className="pm-time-input"
																	/>
																</div>
															</div>
														</div>
													</div>
												)}
											</div>
										);
									})}
								</div>
								
								{/* Add new entry */}
								<div className="pm-daily-task-new">
									<h4>Add New Daily Task Entry</h4>
									<div className="pm-form-group">
										<label>Task Description</label>
										<textarea
											value={newEntry.description}
											onChange={(e) => setNewEntry({...newEntry, description: e.target.value})}
											placeholder="Add task for the day..."
											rows={2}
											className="pm-daily-task-desc"
										/>
									</div>
									<div className="pm-form-group">
										<label>End of Day Report</label>
										<textarea
											value={newEntry.end_of_day_report || ''}
											onChange={(e) => setNewEntry({...newEntry, end_of_day_report: e.target.value})}
											placeholder="Summary of what was accomplished..."
											rows={3}
											className="pm-daily-task-report"
										/>
									</div>
									<div className="pm-daily-task-times">
										<div className="pm-form-group">
											<label>Start Time</label>
											<input
												type="time"
												value={newEntry.start_time}
												onChange={(e) => setNewEntry({...newEntry, start_time: e.target.value})}
												className="pm-time-input"
											/>
										</div>
										<div className="pm-form-group">
											<label>End Time</label>
											<input
												type="time"
												value={newEntry.end_time}
												onChange={(e) => setNewEntry({...newEntry, end_time: e.target.value})}
												className="pm-time-input"
											/>
										</div>
									</div>
									<button
										type="button"
										className="pm-btn pm-btn-secondary pm-btn-sm"
										onClick={addDailyEntry}
									>
										+ Add Entry
									</button>
								</div>
							</div>
						)}
					</div>
					<div className="pm-modal-footer">
						{isEditing && (
							<button 
								type="button" 
								className="pm-btn pm-btn-danger"
								onClick={() => onDelete(editingTask.id)}
							>
								Delete
							</button>
						)}
						<div className="pm-modal-footer-right">
							<button type="button" className="pm-btn pm-btn-ghost" onClick={onClose}>
								Cancel
							</button>
							<button type="submit" className="pm-btn pm-btn-primary">
								{isEditing ? 'Save Changes' : 'Create Task'}
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	);
};

const StatusModal = ({ isEditing, editingStatus, onSubmit, onClose }) => {
	const [formData, setFormData] = useState({
		name: editingStatus?.name || '',
		color: editingStatus?.color || '#6b7280'
	});
	
	const handleSubmit = (e) => {
		e.preventDefault();
		onSubmit(formData);
	};
	
	return (
		<div className="pm-modal-overlay" onClick={onClose}>
			<div className="pm-modal" onClick={e => e.stopPropagation()}>
				<div className="pm-modal-header">
					<h3>{isEditing ? 'Edit Column' : 'New Column'}</h3>
					<button className="pm-modal-close" onClick={onClose}>×</button>
				</div>
				<form onSubmit={handleSubmit}>
					<div className="pm-modal-body">
						<div className="pm-form-group">
							<label>Column Name *</label>
							<input
								type="text"
								value={formData.name}
								onChange={(e) => setFormData({...formData, name: e.target.value})}
								placeholder="e.g., In Progress, Review, Done"
								required
							/>
						</div>
						<div className="pm-form-group">
							<label>Color</label>
							<div className="pm-color-picker">
								{['#6b7280', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4'].map(color => (
									<button
										key={color}
										type="button"
										className={`pm-color-option ${formData.color === color ? 'selected' : ''}`}
										style={{ backgroundColor: color }}
										onClick={() => setFormData({...formData, color})}
									/>
								))}
							</div>
						</div>
					</div>
					<div className="pm-modal-footer">
						<button type="button" className="pm-btn pm-btn-ghost" onClick={onClose}>
							Cancel
						</button>
						<button type="submit" className="pm-btn pm-btn-primary">
							{isEditing ? 'Save Changes' : 'Add Column'}
						</button>
					</div>
				</form>
			</div>
		</div>
	);
};

const ShareModal = ({ project, shareUrl, isPublic, onToggleShare, onRegenerateLink, onClose }) => {
	const [copied, setCopied] = useState(false);
	const [isSharing, setIsSharing] = useState(isPublic);
	
	const handleCopyLink = () => {
		if (shareUrl) {
			navigator.clipboard.writeText(shareUrl).then(() => {
				setCopied(true);
				setTimeout(() => setCopied(false), 2000);
			});
		}
	};
	
	const handleToggleShare = async () => {
		const newState = !isSharing;
		await onToggleShare(newState);
		setIsSharing(newState);
	};
	
	return (
		<div className="pm-modal-overlay" onClick={onClose}>
			<div className="pm-modal" onClick={e => e.stopPropagation()}>
				<div className="pm-modal-header">
					<h3>Share Project</h3>
					<button className="pm-modal-close" onClick={onClose}>×</button>
				</div>
				<div className="pm-modal-body">
					<div className="pm-share-info">
						<p><strong>{project.name}</strong></p>
						<p className="pm-share-desc">
							Share this project publicly. Anyone with the link can view the project board.
						</p>
					</div>
					
					<div className="pm-form-group">
						<label className="pm-toggle-label">
							<input
								type="checkbox"
								checked={isSharing}
								onChange={handleToggleShare}
								className="pm-toggle-input"
							/>
							<span className="pm-toggle-slider"></span>
							<span>Enable Public Sharing</span>
						</label>
					</div>
					
					{isSharing && shareUrl && (
						<div className="pm-share-link-section">
							<label>Public Link</label>
							<div className="pm-share-link-container">
								<input
									type="text"
									value={shareUrl}
									readOnly
									className="pm-share-link-input"
									onClick={(e) => e.target.select()}
								/>
								<button
									type="button"
									className="pm-btn pm-btn-secondary"
									onClick={handleCopyLink}
								>
									{copied ? '✓ Copied!' : 'Copy'}
								</button>
							</div>
							<button
								type="button"
								className="pm-btn pm-btn-ghost pm-btn-sm"
								onClick={onRegenerateLink}
								style={{ marginTop: '8px' }}
							>
								Regenerate Link
							</button>
							<p className="pm-share-warning">
								⚠️ Anyone with this link can view the project. Regenerating the link will invalidate the old one.
							</p>
						</div>
					)}
				</div>
				<div className="pm-modal-footer">
					<button type="button" className="pm-btn pm-btn-primary" onClick={onClose}>
						Done
					</button>
				</div>
			</div>
		</div>
	);
};

// ==================== MAIN COMPONENT ====================

class ProjectManager extends Component {
	static slug = 'dicm_project_manager';

	constructor(props) {
		super(props);
		
		const config = this.props.attrs?.config ? JSON.parse(this.props.attrs.config) : {};
		
		// Get fallback values from wp_localize_script if available
		const wpData = typeof window !== 'undefined' && window.dicmProjectManager ? window.dicmProjectManager : {};
		
		// Check if viewing a shared project via URL parameter
		const urlParams = new URLSearchParams(window.location.search);
		const shareToken = urlParams.get('pm_share');
		
		this.state = {
			// Core data
			projects: [],
			currentProject: null,
			statuses: [],
			tasks: [],
			users: [],
			
			// UI state
			loading: true,
			error: null,
			view: shareToken ? 'public-kanban' : 'projects', // 'projects' | 'kanban' | 'public-kanban'
			
			// Modals
			showProjectModal: false,
			showTaskModal: false,
			showStatusModal: false,
			showShareModal: false,
			editingProject: null,
			editingTask: null,
			editingStatus: null,
			
			// Drag and drop
			draggedTask: null,
			dragOverStatus: null,
			dragOverIndex: null,
			
			// Quick add
			quickAddStatus: null,
			quickAddTitle: '',
			
			// Public share
			shareToken: shareToken,
			isPublicView: !!shareToken,
			
			// Config - use config from props, fallback to wp_localize_script data
			config: {
				moduleInstanceId: config.moduleInstanceId || '',
				boardTitle: config.boardTitle || 'Project Manager',
				allowCreateProjects: config.allowCreateProjects !== false,
				defaultStatuses: config.defaultStatuses || [],
				priorityColors: config.priorityColors || {},
				columnMinWidth: config.columnMinWidth || 280,
				columnBgColor: config.columnBgColor || '#f3f4f6',
				cardBgColor: config.cardBgColor || '#ffffff',
				userId: config.userId || wpData.userId || 0,
				isAdmin: config.isAdmin || false,
				ajaxUrl: config.ajaxUrl || wpData.ajaxUrl || '',
				nonce: config.nonce || wpData.nonce || '',
				userName: config.userName || 'User',
				userAvatar: config.userAvatar || ''
			}
		};
	}

	componentDidMount() {
		const { ajaxUrl, nonce } = this.state.config;
		const { isPublicView, shareToken } = this.state;
		
		// Debug logging
		console.log('ProjectManager: Initializing with config:', {
			ajaxUrl: ajaxUrl ? 'set' : 'missing',
			nonce: nonce ? 'set' : 'missing',
			jQueryAvailable: typeof window.jQuery !== 'undefined',
			isPublicView,
			shareToken: shareToken ? 'set' : 'missing'
		});
		
		// Load public shared project if viewing via share link
		if (isPublicView && shareToken) {
			this.loadSharedProject(shareToken);
		}
		// Only load data if we have valid config (authenticated users)
		else if (ajaxUrl && nonce) {
			this.loadProjects();
			this.loadUsers();
		} else {
			console.error('ProjectManager: Missing ajaxUrl or nonce in config');
			this.setState({ 
				error: 'Configuration error. Please refresh the page.',
				loading: false 
			});
		}
	}

	// ==================== API CALLS ====================

	/**
	 * Make an AJAX call using jQuery if available, otherwise fetch
	 * @param {string} action - The WordPress AJAX action name
	 * @param {object} data - Data to send with the request
	 */
	apiCall = async (action, data = {}) => {
		const { ajaxUrl, nonce } = this.state.config;
		
		if (!ajaxUrl || !nonce) {
			throw new Error('AJAX configuration is missing');
		}

		// Build the data object for WordPress AJAX
		const ajaxData = {
			action: action,
			nonce: nonce,
			...data
		};

		// Try jQuery AJAX first (most reliable with WordPress)
		if (typeof window.jQuery !== 'undefined') {
			return this.jQueryAjax(ajaxUrl, ajaxData);
		}
		
		// Fallback to fetch with URLSearchParams
		return this.fetchAjax(ajaxUrl, ajaxData);
	}

	/**
	 * jQuery-based AJAX call (most compatible with WordPress)
	 */
	jQueryAjax = (url, data) => {
		return new Promise((resolve, reject) => {
			window.jQuery.ajax({
				url: url,
				type: 'POST',
				data: data,
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						resolve(response.data);
					} else {
						reject(new Error(response.data?.message || 'Request failed'));
					}
				},
				error: function(xhr, status, error) {
					console.error('ProjectManager jQuery AJAX error:', status, error, xhr.responseText);
					reject(new Error(`AJAX Error: ${status} - ${error}`));
				}
			});
		});
	}

	/**
	 * Fetch-based AJAX call (fallback)
	 */
	fetchAjax = async (url, data) => {
		// Use URLSearchParams for proper encoding
		const params = new URLSearchParams();
		
		const appendData = (obj, prefix = '') => {
			Object.keys(obj).forEach(key => {
				const value = obj[key];
				const paramKey = prefix ? `${prefix}[${key}]` : key;
				
				if (Array.isArray(value)) {
					value.forEach((item, index) => {
						if (typeof item === 'object' && item !== null) {
							Object.keys(item).forEach(k => {
								params.append(`${paramKey}[${index}][${k}]`, String(item[k]));
							});
						} else {
							params.append(`${paramKey}[]`, String(item));
						}
					});
				} else if (typeof value === 'object' && value !== null) {
					appendData(value, paramKey);
				} else if (value !== null && value !== undefined && value !== '') {
					params.append(paramKey, String(value));
				}
			});
		};
		
		appendData(data);
		
		const response = await fetch(url, {
			method: 'POST',
			body: params,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			}
		});
		
		if (!response.ok) {
			const text = await response.text();
			console.error('ProjectManager fetch error:', response.status, text.substring(0, 500));
			throw new Error(`HTTP ${response.status}: ${response.statusText}`);
		}
		
		const text = await response.text();
		let result;
		
		try {
			result = JSON.parse(text);
		} catch (e) {
			console.error('ProjectManager: Invalid JSON:', text.substring(0, 200));
			throw new Error('Invalid server response');
		}
		
		if (!result.success) {
			throw new Error(result.data?.message || 'Request failed');
		}
		
		return result.data;
	}

	loadProjects = async () => {
		try {
			this.setState({ loading: true, error: null });
			const data = await this.apiCall('pm_get_projects');
			this.setState({ projects: data.projects, loading: false });
		} catch (error) {
			this.setState({ error: error.message, loading: false });
		}
	}

	loadUsers = async () => {
		try {
			const data = await this.apiCall('pm_get_users');
			this.setState({ users: data.users });
		} catch (error) {
			console.error('Failed to load users:', error);
		}
	}

	loadProjectData = async (projectId) => {
		try {
			this.setState({ loading: true, error: null });
			
			const [statusesData, tasksData] = await Promise.all([
				this.apiCall('pm_get_statuses', { project_id: projectId }),
				this.apiCall('pm_get_tasks', { project_id: projectId })
			]);
			
			// Load daily task entries for each task
			const tasksWithEntries = await Promise.all(
				tasksData.tasks.map(async (task) => {
					const entries = await this.loadDailyTaskEntries(task.id);
					return { ...task, dailyEntries: entries };
				})
			);
			
			this.setState({
				statuses: statusesData.statuses,
				tasks: tasksWithEntries,
				loading: false
			});
		} catch (error) {
			this.setState({ error: error.message, loading: false });
		}
	}

	loadSharedProject = async (shareToken) => {
		try {
			this.setState({ loading: true, error: null });
			
			const { ajaxUrl } = this.state.config;
			
			// For public view, we don't need nonce verification
			const params = new URLSearchParams();
			params.append('action', 'pm_get_shared_project');
			params.append('share_token', shareToken);
			
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: params,
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				}
			});
			
			const text = await response.text();
			let result;
			
			try {
				result = JSON.parse(text);
			} catch (e) {
				throw new Error('Invalid server response');
			}
			
			if (!result.success) {
				throw new Error(result.data?.message || 'Failed to load shared project');
			}
			
			this.setState({
				currentProject: result.data.project,
				statuses: result.data.statuses,
				tasks: result.data.tasks,
				loading: false,
				view: 'public-kanban'
			});
		} catch (error) {
			this.setState({ 
				error: error.message, 
				loading: false,
				view: 'error'
			});
		}
	}

	// ==================== PROJECT HANDLERS ====================

	selectProject = (project) => {
		this.setState({
			currentProject: project,
			view: 'kanban'
		}, () => {
			this.loadProjectData(project.id);
		});
	}

	backToProjects = () => {
		this.setState({
			view: 'projects',
			currentProject: null,
			statuses: [],
			tasks: []
		});
	}

	handleProjectSubmit = async (projectData) => {
		const { editingProject } = this.state;
		
		try {
			this.setState({ loading: true });
			
			if (editingProject) {
				await this.apiCall('pm_update_project', {
					project_id: editingProject.id,
					name: projectData.name,
					description: projectData.description,
					color: projectData.color
				});
				
				// Update current project if editing the current one
				if (this.state.currentProject?.id === editingProject.id) {
					const updated = { ...this.state.currentProject, ...projectData };
					this.setState({ currentProject: updated });
				}
			} else {
				await this.apiCall('pm_create_project', {
					name: projectData.name,
					description: projectData.description,
					color: projectData.color,
					default_statuses: this.state.config.defaultStatuses
				});
			}
			
			await this.loadProjects();
			this.setState({ showProjectModal: false, editingProject: null });
		} catch (error) {
			this.setState({ error: error.message, loading: false });
		}
	}

	deleteProject = async (projectId) => {
		if (!window.confirm('Are you sure you want to delete this project? All tasks will be permanently deleted.')) {
			return;
		}
		
		try {
			this.setState({ loading: true });
			await this.apiCall('pm_delete_project', { project_id: projectId });
			
			if (this.state.currentProject?.id === projectId) {
				this.backToProjects();
			}
			
			await this.loadProjects();
		} catch (error) {
			this.setState({ error: error.message, loading: false });
		}
	}

	// ==================== SHARE HANDLERS ====================

	toggleProjectShare = async (isPublic) => {
		const { currentProject } = this.state;
		
		try {
			const response = await this.apiCall('pm_toggle_project_share', { 
				project_id: currentProject.id,
				is_public: isPublic ? 1 : 0
			});
			
			// Update current project with new share settings
			this.setState(prev => ({
				currentProject: {
					...prev.currentProject,
					is_public: response.is_public,
					share_token: response.share_token,
					share_url: response.share_url
				}
			}));
			
			return response;
		} catch (error) {
			this.setState({ error: error.message });
			throw error;
		}
	}

	regenerateShareLink = async () => {
		const { currentProject } = this.state;
		
		if (!window.confirm('Regenerate share link? The old link will stop working.')) {
			return;
		}
		
		try {
			const response = await this.apiCall('pm_regenerate_share_token', { 
				project_id: currentProject.id
			});
			
			// Update current project with new share token
			this.setState(prev => ({
				currentProject: {
					...prev.currentProject,
					share_token: response.share_token,
					share_url: response.share_url
				}
			}));
			
			return response;
		} catch (error) {
			this.setState({ error: error.message });
			throw error;
		}
	}

	// ==================== STATUS HANDLERS ====================

	handleStatusSubmit = async (statusData) => {
		const { editingStatus, currentProject } = this.state;
		
		try {
			if (editingStatus) {
				await this.apiCall('pm_update_status', {
					status_id: editingStatus.id,
					name: statusData.name,
					color: statusData.color
				});
			} else {
				await this.apiCall('pm_create_status', {
					project_id: currentProject.id,
					name: statusData.name,
					color: statusData.color
				});
			}
			
			await this.loadProjectData(currentProject.id);
			this.setState({ showStatusModal: false, editingStatus: null });
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	deleteStatus = async (statusId) => {
		const status = this.state.statuses.find(s => s.id === statusId);
		const tasksInStatus = this.state.tasks.filter(t => String(t.status_id) === String(statusId));
		
		let moveTasksTo = null;
		
		if (tasksInStatus.length > 0) {
			const otherStatuses = this.state.statuses.filter(s => s.id !== statusId);
			if (otherStatuses.length === 0) {
				alert('Cannot delete the only status. Please create another status first.');
				return;
			}
			
			const confirmDelete = window.confirm(
				`This status has ${tasksInStatus.length} task(s). They will be moved to the first available status. Continue?`
			);
			
			if (!confirmDelete) return;
			moveTasksTo = otherStatuses[0].id;
		} else {
			if (!window.confirm(`Delete status "${status.name}"?`)) return;
		}
		
		try {
			await this.apiCall('pm_delete_status', {
				status_id: statusId,
				move_tasks_to: moveTasksTo
			});
			
			await this.loadProjectData(this.state.currentProject.id);
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	// ==================== DAILY TASK ENTRIES HANDLERS ====================

	loadDailyTaskEntries = async (taskId) => {
		try {
			const data = await this.apiCall('pm_get_daily_task_entries', { task_id: taskId });
			return data.entries || [];
		} catch (error) {
			console.error('Failed to load daily task entries:', error);
			return [];
		}
	}

	handleDailyTasksChange = async (taskId, entries, deletedIds = []) => {
		// Save daily task entries to the server
		try {
			const { tasks } = this.state;
			const task = tasks.find(t => t.id === taskId);
			if (!task) return;

			// Delete entries from server first
			for (const entryId of deletedIds) {
				try {
					await this.apiCall('pm_delete_daily_task_entry', { entry_id: entryId });
				} catch (error) {
					console.error('Failed to delete entry:', error);
				}
			}

			// Save each entry to the server
			for (const entry of entries) {
				if (entry.isNew && !entry.savedToServer) {
					const response = await this.apiCall('pm_create_daily_task_entry', {
						task_id: taskId,
						description: entry.description || '',
						start_time: entry.start_time,
						end_time: entry.end_time
					});
					// Update entry with server ID
					entry.id = response.entry.id;
					entry.created_at = response.entry.created_at;
					entry.savedToServer = true;
					entry.isNew = false;
					delete entry.tempId;
				} else if (entry.id && entry.modified) {
					await this.apiCall('pm_update_daily_task_entry', {
						entry_id: entry.id,
						description: entry.description || '',
						start_time: entry.start_time,
						end_time: entry.end_time
					});
					entry.modified = false;
				}
			}
			
			// Update local state after all operations
			this.setState(prev => ({
				tasks: prev.tasks.map(t => 
					t.id === taskId ? { ...t, dailyEntries: entries } : t
				)
			}));
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	deleteDailyTaskEntry = async (entryId) => {
		try {
			await this.apiCall('pm_delete_daily_task_entry', { entry_id: entryId });
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	// ==================== TASK HANDLERS ====================

	handleTaskSubmit = async (taskData, isEditing) => {
		const { editingTask, currentProject } = this.state;
		
		try {
			if (isEditing) {
				const data = await this.apiCall('pm_update_task', {
					task_id: editingTask.id,
					title: taskData.title,
					description: taskData.description || '',
					priority: taskData.priority || 'Medium',
					due_date: taskData.due_date || '',
					assignee_id: taskData.assignee_id || ''
				});
				
				this.setState(prev => ({
					tasks: prev.tasks.map(t => t.id === data.task.id ? data.task : t),
					showTaskModal: false,
					editingTask: null
				}));
			} else {
				const data = await this.apiCall('pm_create_task', {
					project_id: currentProject.id,
					status_id: taskData.status_id,
					title: taskData.title,
					description: taskData.description || '',
					priority: taskData.priority || 'Medium',
					due_date: taskData.due_date || '',
					assignee_id: taskData.assignee_id || ''
				});
				
				this.setState(prev => ({
					tasks: [...prev.tasks, data.task],
					showTaskModal: false,
					editingTask: null,
					quickAddStatus: null,
					quickAddTitle: ''
				}));
			}
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	deleteTask = async (taskId) => {
		if (!window.confirm('Delete this task?')) return;
		
		try {
			await this.apiCall('pm_delete_task', { task_id: taskId });
			
			this.setState(prev => ({
				tasks: prev.tasks.filter(t => t.id !== taskId),
				showTaskModal: false,
				editingTask: null
			}));
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	quickAddTask = async (statusId) => {
		const { quickAddTitle, currentProject } = this.state;
		if (!quickAddTitle.trim()) return;
		
		try {
			const data = await this.apiCall('pm_create_task', {
				project_id: currentProject.id,
				status_id: statusId,
				title: quickAddTitle.trim(),
				description: '',
				priority: 'Medium',
				due_date: '',
				assignee_id: ''
			});
			
			this.setState(prev => ({
				tasks: [...prev.tasks, data.task],
				quickAddStatus: null,
				quickAddTitle: ''
			}));
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	// ==================== DRAG AND DROP ====================

	handleDragStart = (e, task) => {
		this.setState({ draggedTask: task });
		e.dataTransfer.effectAllowed = 'move';
		e.target.classList.add('pm-dragging');
	}

	handleDragEnd = (e) => {
		e.target.classList.remove('pm-dragging');
		this.setState({
			draggedTask: null,
			dragOverStatus: null,
			dragOverIndex: null
		});
	}

	handleDragOver = (e, statusId, index = null) => {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		
		this.setState({
			dragOverStatus: statusId,
			dragOverIndex: index
		});
	}

	handleDrop = async (e, statusId, dropIndex = null) => {
		e.preventDefault();
		
		const { draggedTask, tasks } = this.state;
		if (!draggedTask) return;
		
		const tasksInStatus = tasks
			.filter(t => String(t.status_id) === String(statusId))
			.sort((a, b) => a.order_index - b.order_index);
		
		let newOrder = 0;
		if (dropIndex !== null && dropIndex < tasksInStatus.length) {
			newOrder = dropIndex;
		} else {
			newOrder = tasksInStatus.length;
		}
		
		try {
			await this.apiCall('pm_move_task', {
				task_id: draggedTask.id,
				new_status_id: statusId,
				new_order: newOrder
			});
			
			this.setState(prev => {
				const updatedTasks = prev.tasks.map(t => {
					if (t.id === draggedTask.id) {
						return { ...t, status_id: statusId, order_index: newOrder };
					}
					return t;
				});
				
				return {
					tasks: updatedTasks,
					draggedTask: null,
					dragOverStatus: null,
					dragOverIndex: null
				};
			});
		} catch (error) {
			this.setState({ error: error.message });
		}
	}

	// ==================== RENDER HELPERS ====================

	getPriorityColor = (priority) => {
		return this.state.config.priorityColors[priority] || '#6b7280';
	}

	formatDate = (dateStr) => {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
	}

	isOverdue = (dateStr) => {
		if (!dateStr) return false;
		const today = new Date();
		today.setHours(0, 0, 0, 0);
		const dueDate = new Date(dateStr);
		return dueDate < today;
	}

	getTasksForStatus = (statusId) => {
		return this.state.tasks
			.filter(t => String(t.status_id) === String(statusId))
			.sort((a, b) => a.order_index - b.order_index);
	}

	// ==================== RENDER METHODS ====================

	renderProjectsView() {
		const { projects, config, loading, showProjectModal, editingProject } = this.state;
		
		return (
			<div className="pm-projects-view">
				<div className="pm-header">
					<h2>{config.boardTitle}</h2>
					{config.isAdmin && (
						<button 
							className="pm-btn pm-btn-primary"
							onClick={() => this.setState({ showProjectModal: true, editingProject: null })}
						>
							<span className="pm-icon">+</span> New Project
						</button>
					)}
				</div>
				
				{loading ? (
					<div className="pm-loading">
						<div className="pm-loading-spinner"></div>
					</div>
				) : projects.length === 0 ? (
					<div className="pm-empty-state">
						<div className="pm-empty-icon">📋</div>
						<h3>No Projects Yet</h3>
						<p>Create your first project to get started</p>
						{config.allowCreateProjects && (
							<button 
								className="pm-btn pm-btn-primary"
								onClick={() => this.setState({ showProjectModal: true })}
							>
								Create Project
							</button>
						)}
					</div>
				) : (
					<div className="pm-projects-grid">
						{projects.map(project => (
							<div 
								key={project.id} 
								className="pm-project-card"
								onClick={() => this.selectProject(project)}
							>
								<div 
									className="pm-project-color-bar" 
									style={{ backgroundColor: project.color }}
								></div>
								<div className="pm-project-content">
									<h3>{project.name}</h3>
									{project.description && (
										<p className="pm-project-desc">{project.description}</p>
									)}
									<div className="pm-project-meta">
										<span className="pm-project-owner">
											Created by {project.owner_name}
										</span>
									</div>
								</div>
								{(project.can_manage || project.is_owner) && (
									<div className="pm-project-actions" onClick={e => e.stopPropagation()}>
										<button 
											className="pm-btn-icon"
											onClick={() => this.setState({ 
												showProjectModal: true, 
												editingProject: project 
											})}
											title="Edit Project"
										>
											✏️
										</button>
										<button 
											className="pm-btn-icon pm-btn-danger"
											onClick={() => this.deleteProject(project.id)}
											title="Delete Project"
										>
											🗑️
										</button>
									</div>
								)}
							</div>
						))}
					</div>
				)}
				
				{showProjectModal && (
					<ProjectModal
						isEditing={!!editingProject}
						editingProject={editingProject}
						onSubmit={this.handleProjectSubmit}
						onClose={() => this.setState({ showProjectModal: false, editingProject: null })}
					/>
				)}
			</div>
		);
	}

	renderKanbanView() {
		const { 
			currentProject, statuses, loading, config,
			showTaskModal, showStatusModal, showShareModal, quickAddStatus, quickAddTitle,
			dragOverStatus, editingTask, editingStatus, users
		} = this.state;
		
		return (
			<div className="pm-kanban-view">
				<div className="pm-header">
					<div className="pm-header-left">
						<button 
							className="pm-btn pm-btn-ghost"
							onClick={this.backToProjects}
						>
							← Back
						</button>
						<h2 style={{ color: currentProject?.color }}>
							{currentProject?.name}
						</h2>
					</div>
					<div className="pm-header-right">
						{currentProject?.is_owner && (
							<button 
								className="pm-btn pm-btn-ghost"
								onClick={() => this.setState({ showShareModal: true })}
								title="Share Project"
							>
								🔗 Share
							</button>
						)}
						{config.isAdmin && (
							<button 
								className="pm-btn pm-btn-secondary"
								onClick={() => this.setState({ showStatusModal: true, editingStatus: null })}
							>
								+ Add Column
							</button>
						)}
					</div>
				</div>
				
				{loading ? (
					<div className="pm-loading">
						<div className="pm-loading-spinner"></div>
					</div>
				) : (
					<div className="pm-kanban-board">
						{statuses.map(status => {
							const statusTasks = this.getTasksForStatus(status.id);
							const isDropTarget = String(dragOverStatus) === String(status.id);
							
							return (
								<div 
									key={status.id}
									className={`pm-kanban-column ${isDropTarget ? 'pm-drop-target' : ''}`}
									style={{ 
										minWidth: config.columnMinWidth,
										backgroundColor: config.columnBgColor
									}}
									onDragOver={(e) => this.handleDragOver(e, status.id)}
									onDrop={(e) => this.handleDrop(e, status.id)}
								>
									<div className="pm-column-header">
										<div 
											className="pm-column-color" 
											style={{ backgroundColor: status.color }}
										></div>
										<span className="pm-column-name">{status.name}</span>
										<span className="pm-column-count">{statusTasks.length}</span>
										{config.isAdmin && (
											<div className="pm-column-actions">
												<button 
													className="pm-btn-icon-sm"
													onClick={() => this.setState({ 
														showStatusModal: true, 
														editingStatus: status 
													})}
													title="Edit Column"
												>
													✏️
												</button>
												<button 
													className="pm-btn-icon-sm pm-btn-danger"
													onClick={() => this.deleteStatus(status.id)}
													title="Delete Column"
												>
													×
												</button>
											</div>
										)}
									</div>
									
									<div className="pm-column-tasks">
										{statusTasks.map((task, index) => (
											<div
												key={task.id}
												className="pm-task-card"
												style={{ backgroundColor: config.cardBgColor }}
												draggable
												onDragStart={(e) => this.handleDragStart(e, task)}
												onDragEnd={this.handleDragEnd}
												onDragOver={(e) => this.handleDragOver(e, status.id, index)}
												onClick={() => this.setState({ 
													showTaskModal: true, 
													editingTask: task 
												})}
											>
												<div 
													className="pm-task-priority"
													style={{ backgroundColor: this.getPriorityColor(task.priority) }}
													title={task.priority}
												></div>
												<div className="pm-task-content">
													<h4 className="pm-task-title">{task.title}</h4>
													{task.description && (
														<p className="pm-task-desc">
															{task.description.substring(0, 80)}
															{task.description.length > 80 ? '...' : ''}
														</p>
													)}
													<div className="pm-task-meta">
														{task.due_date && (
															<span className={`pm-task-due ${this.isOverdue(task.due_date) ? 'pm-overdue' : ''}`}>
																📅 {this.formatDate(task.due_date)}
															</span>
														)}
														{task.assignee_id && (
															<img 
																src={task.assignee_avatar} 
																alt={task.assignee_name}
																className="pm-task-assignee"
																title={task.assignee_name}
															/>
														)}
													</div>
												</div>
											</div>
										))}
										
										{/* Drop zone indicator */}
										{isDropTarget && (
											<div className="pm-drop-indicator"></div>
										)}
									</div>
									
									{/* Quick add */}
									{quickAddStatus === status.id ? (
										<div className="pm-quick-add">
											<input
												type="text"
												placeholder="Task title..."
												value={quickAddTitle}
												onChange={(e) => this.setState({ quickAddTitle: e.target.value })}
												onKeyDown={(e) => {
													if (e.key === 'Enter') this.quickAddTask(status.id);
													if (e.key === 'Escape') this.setState({ quickAddStatus: null, quickAddTitle: '' });
												}}
												autoFocus
											/>
											<div className="pm-quick-add-actions">
												<button 
													className="pm-btn pm-btn-primary pm-btn-sm"
													onClick={() => this.quickAddTask(status.id)}
												>
													Add
												</button>
												<button 
													className="pm-btn pm-btn-ghost pm-btn-sm"
													onClick={() => this.setState({ quickAddStatus: null, quickAddTitle: '' })}
												>
													Cancel
												</button>
											</div>
										</div>
									) : (
										<button 
											className="pm-add-task-btn"
											onClick={() => this.setState({ quickAddStatus: status.id, quickAddTitle: '' })}
										>
											+ Add Task
										</button>
									)}
								</div>
							);
						})}
						
						{/* Add column button - only for admins */}
						{config.isAdmin && (
							<div 
								className="pm-kanban-column pm-add-column"
								onClick={() => this.setState({ showStatusModal: true, editingStatus: null })}
							>
								<span>+ Add Column</span>
							</div>
						)}
					</div>
				)}
				
				{showTaskModal && (
					<TaskModal
						editingTask={editingTask}
						statuses={statuses}
						users={users}
						priorityColors={config.priorityColors}
						onSubmit={this.handleTaskSubmit}
						onDelete={this.deleteTask}
						onDailyTasksChange={this.handleDailyTasksChange}
						onClose={() => this.setState({ showTaskModal: false, editingTask: null })}
					/>
				)}
				
				{showStatusModal && (
					<StatusModal
						isEditing={!!editingStatus}
						editingStatus={editingStatus}
						onSubmit={this.handleStatusSubmit}
						onClose={() => this.setState({ showStatusModal: false, editingStatus: null })}
					/>
				)}
				
				{showShareModal && (
					<ShareModal
						project={currentProject}
						shareUrl={currentProject?.share_url || (currentProject?.is_public && currentProject?.share_token ? `${window.location.origin}?pm_share=${currentProject.share_token}` : null)}
						isPublic={!!currentProject?.is_public}
						onToggleShare={this.toggleProjectShare}
						onRegenerateLink={this.regenerateShareLink}
						onClose={() => this.setState({ showShareModal: false })}
					/>
				)}
			</div>
		);
	}

	renderPublicKanbanView() {
		const { currentProject, statuses, tasks, loading, config } = this.state;
		
		return (
			<div className="pm-kanban-view pm-public-view">
				<div className="pm-header">
					<div className="pm-header-left">
						<h2 style={{ color: currentProject?.color }}>
							{currentProject?.name}
						</h2>
						<span className="pm-public-badge">Public View (Read Only)</span>
					</div>
					<div className="pm-header-right">
						<span className="pm-project-owner">
							by {currentProject?.owner_name}
						</span>
					</div>
				</div>
				
				{loading ? (
					<div className="pm-loading">
						<div className="pm-loading-spinner"></div>
					</div>
				) : (
					<div className="pm-kanban-board">
						{statuses.map(status => {
							const statusTasks = tasks.filter(t => String(t.status_id) === String(status.id))
								.sort((a, b) => a.order_index - b.order_index);
							
							return (
								<div 
									key={status.id} 
									className="pm-kanban-column"
									style={{ 
										minWidth: `${config.columnMinWidth}px`,
										backgroundColor: config.columnBgColor 
									}}
								>
									<div className="pm-kanban-column-header" style={{ borderTopColor: status.color }}>
										<h3>{status.name}</h3>
										<span className="pm-task-count">{statusTasks.length}</span>
									</div>
									
									<div className="pm-kanban-column-content">
										{statusTasks.map(task => (
											<div 
												key={task.id} 
												className="pm-task-card"
												style={{ backgroundColor: config.cardBgColor }}
											>
												<div className="pm-task-header">
													<h4>{task.title}</h4>
													<span 
														className="pm-priority-badge"
														style={{ 
															backgroundColor: this.getPriorityColor(task.priority) + '20',
															color: this.getPriorityColor(task.priority)
														}}
													>
														{task.priority}
													</span>
												</div>
												
												{task.description && (
													<p className="pm-task-description">{task.description}</p>
												)}
												
												<div className="pm-task-meta">
													{task.due_date && (
														<span className={`pm-due-date ${this.isOverdue(task.due_date) ? 'overdue' : ''}`}>
															📅 {this.formatDate(task.due_date)}
														</span>
													)}
													
													{task.assignee_id && (
														<div className="pm-task-assignee">
															<img 
																src={task.assignee_avatar} 
																alt={task.assignee_name}
																className="pm-avatar"
															/>
															<span>{task.assignee_name}</span>
														</div>
													)}
												</div>
											</div>
										))}
										
										{statusTasks.length === 0 && (
											<div className="pm-empty-column">
												No tasks
											</div>
										)}
									</div>
								</div>
							);
						})}
					</div>
				)}
			</div>
		);
	}

	render() {
		const { view, error } = this.state;
		
		return (
			<div className="pm-container">
				{error && (
					<div className="pm-error-toast">
						<span>{error}</span>
						<button onClick={() => this.setState({ error: null })}>×</button>
					</div>
				)}
				
				{view === 'projects' && this.renderProjectsView()}
				{view === 'kanban' && this.renderKanbanView()}
				{view === 'public-kanban' && this.renderPublicKanbanView()}
				{view === 'error' && (
					<div className="pm-empty-state">
						<div className="pm-empty-icon">❌</div>
						<h3>Project Not Found</h3>
						<p>This project may not exist or is not publicly shared.</p>
					</div>
				)}
			</div>
		);
	}
}

export default ProjectManager;
