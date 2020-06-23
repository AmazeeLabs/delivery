# Delivery

[![Build Status](https://travis-ci.org/AmazeeLabs/delivery.svg?branch=8.x-1.x)](https://travis-ci.org/AmazeeLabs/delivery) [![codecov](https://codecov.io/gh/AmazeeLabs/delivery/branch/8.x-1.x/graph/badge.svg)](https://codecov.io/gh/AmazeeLabs/delivery) 

# Module dependencies:
  - language
  - workspaces
  - revision_tree
  - content_moderation
  - entity_usage
  - conflict

# Installation
- on pre-install: for all the entity types with workspaces support, the _deleted_ field is added to _revision_metadata_keys_.
- on install: it drops the _menu_tree_ table and rebuilds the menu.

# Implemented hooks:
 - **delivery_query_workspace_sensitive_alter**: for the select queries which should not return all revisions, it adds an inner join between the _workspace_association_ and the entity revision table.
 - **delivery_entity_access**:
   - for the 'deliver' operation, the 'deliver items' permission is checked.
   - if the entity has workspace support, it will forbid the access for any operation, in case the entity is not tracked by the current worskspace.
   - if the entity is a delivery item, it will check the access to the delivery entity, for the same operation.
 - **delivery_entity_type_build**:
   - changes some handler classes for the menu_link_content (Make sure to associate before running postSave so workspace associations are up to date before the menu tree rebuild.) and workspace (WorkspaceListBuilder).
   - removes some constraints from the entity types: EntityWorkspaceConflict, EntityChanged, MenuTreeHierarchy, EntityUntranslatableFields
 - **delivery_delivery_view_alter**: embeds the _delivery_status_ view.
 - **delivery_form_field_config_edit_form_alter**: Adds the 'Exclude this field from conflict resolution.' as a third party setting on fields.
 - **delivery_form_alter**:
   - sets the workspace_safe flag om the forms (on all the forms!)
   - on entity forms, it sets the default language to the current workspace primary language (it uses fields created by workspace_allowed_langauges module, most probably this logic needs to be moved somewhere else).
 - **delivery_form_menu_edit_form_alter**: shows a message about unpublished menu changes.
 - **delivery_entity_bundle_info_alter**: Disable the translation on moderation states.
 - **delivery_entity_base_field_info_alter**:
    - Make sure all languages are always in the same moderation state.
    - Make sure all languages are simultaneously published.
    - The weight, expanded, enabled and parent fields of menu_link_content are set to be revisionable.
 - **delivery_entity_base_field_info**:
   - it adds the assigned_workspaces field to the user entity type.
   - it adds the auto_push field to the workspace entity type.
   - it adds the deleted field to all the entity types with workspaces support, for the soft delete functionality.
 - **delivery_entity_update**: seems to implement the autopush functionality when a revision reaches in a 'default revision' state.
 - **delivery_entity_load**: updates the default revision flag based on the fact that the tracked revision of the entity for the current workspace is the same as the entity being loaded.
 - **delivery_entity_predelete**: implements the soft delete functionality.

# Overwritten services (ServiceProvider class):
 - **paramconverter.entity** (DeliveryEntityConverter class):
   - unsets the load_latest_revision flag, so the latest revision is not loaded, even though content moderation might tell us to.
   - loads the result from the parent class and checks if the entity is deleted (uses the delete field from the soft delete functionality).
 - **paramconverter.latest_revision** => DeliveryEntityConverter (same as paramconverter.entity)
 - **paramconverter.entity_revision** => DeliveryEntityConverter (same as paramconverter.entity)
 - **content_translation.overview_access** (DeliveryContentTranslationOverviewAccess class)
   - blocks the access if the current workspace has only one language assigned.
 - **entity_usage.usage** (DeliveryEntityUsage class):
   - new implementation for the listSources() method of the parent class to include information about workspaces.
 - **workspaces.manager** (DeliveryWorkspaceManager class):
   - overwrites the executeInWorkspace() and doSwitchWorkspace() methods to add a flag called 'safe' which identifies an operation as being safe to be executed in the respective workspace, so the user access to the workspace is skipped.

# Implemented services:
 - **delivery.workspace_assignment**: a helper service that has a public method to return the workspaces of an user (including all the children workspaces of the directly assigned workspaces).
 - **delivery.service**: this seems to be the main service of the class, it can do a lot of things:
   - forwards a delivery (forwardDelivery)
   - checks if a delivery has conflicts (deliveryHasConflicts)
   - checks if a delivery has pending changes. (deliveryHasPendingChanges)
   - checks if a delivery has any change (pending, conflict, outdated changes). (deliveryHasChanges)
   - returns the list of modified entities of a delivery (getModifiedEntities)
   - returns the list of nodes and media ids from a delivery. (getNodeIDsFromDelivery, getMediaIDsFromDelivery)
   - pull all the changes from a delivery into a workspace (pullChangesFromDeliveryToWorkspace)
   - checks if a delivery item has conflicts (deliverItemHasConflicts)
   - force push a delivery item (acceptDeliveryItem)
   - force decline a delivery item (declineDeliveryItem)
   - merges a delivery item (mergeDeliveryItem)
   - checks if a delivery can be pulled into the current workspace (canPullDelivery)
   - return a delivery data by workspace (getDeliveryDataByWorkspace)
   - checks if the entity is inherited (getEntityInheritedFlag)
 - **delivery.route_subscriber**:
   - alters the node controller so that on the revisions list route it lists only its ancestors, not all the revisions.
   - alters the entity usage local task route so that it adds the workspace information in the restulting table.
 - **conflict_resolution.merge_invisible_fields**: Automatically resolves conflicts in invisible fields.
 - **conflict_resolution.merge_blacklisted_fields**: Automatically resolves conflicts in blacklisted fields.
 - **CONFLICT_DISCOVERY.BLACKLISTED**: not sure, what's the difference with conflict_resolution.merge_blacklisted_fields ? They both extend the same class, but one is a merge strategy and one is a conflict resolution plugin.
 - **DRUPAL\WORKSPACES\ENTITYOPERATIONS**: not sure what this is...
 - **delivery.filtered_language_manager**: a language manager service which extends the ConfigurableLanguageManager and, if the workspace has any assigned languages, will return only results that matches those assigned languages.
 - **delivery.content_translation.route_subscriber** (DeliveryContentTranslationRouteSubscriber class):
   - alters the content translation overview routes of all the entity types to use the DeliveryContentTranslationController class. This class completely overwrites the overview methods, and also uses the previous language manager service (delivery.filtered_language_manager)
 - **delivery.plugin.manager.menu.link**: decorates the plugin.manager.menu.link service and basically just enhances the getDefinitions() method so that the discovery of the menu links is done in the default workspace (live).
 - **delivery.menu.tree_storage**: Overrides the default menu storage to provide workspace-specific menu links:
   - loadTreeData(): Adds any non-default workspace as a menu tree condition parameter so it is included in the cache ID.
   - loadLinks(): Replaces the menu link plugin definitions with workspace-specific ones.
   - adds the 'workspace' field into the schema definition (the schemaDefinition() method).
   - the doSave() is overwritten so that when a menu_link_content entity gets saved, the workspace field of all the related menu link content entities gets updated? Not sure actually what's the benefit of this method, or why do we need it.
   - in the preSave() the workspace information is added to the fields that will be saved.

# Implemented plugins:
 - views fields: inherited_content_field, delivery_item_status, delivery_item_label, delivery_delivery_status
 - views filters: current_workspace_filter, delivery_active_workspace_filter (Override the query to ensure the active workspace is being used if needed.), inherited_content_filter, relevant_delivery_items, delivery_workspaces_list
 - views relationship: delivery_workspace_revision

# Routes:
 - **delivery.settings**: a settings form where the user can set the 'workspace_pages' (Define a set of workspace sensitive pages. All other pages will be using the default workspace). However, this setting seems to not be used in the module or in the daimler custom code.
 - **entity.delivery.canonical, entity.delivery.collection, entity.delivery.edit_form**: Delivery entity type links.
 - **delivery.delivery_add**: Delivery add page.
 - **delivery.workspace_delivery_controller**: Creates a new delivery from all modified nodes and media entities. The source workspace is get from the current request, and the target is its parent.
 - **delivery.delivery_forward**: Form to forward a delivery to another target workspace.
 - **delivery.delivery_pull**: Form to pull the changes of a delivery in the current workspace.
 - **delivery.delivery_push**: Form to push a delivery. One strange thing is that one of the batch operations is the 'pushChangesBatch' method of the form, but this does not exist.
 - **delivery.menu_push**: Form to push the changed menu link content entities to the parent workspace. Seems to be some kind of import, the 'syncing' flag is set when the save operation is run in the parent workspace.
 - **delivery_item.resolve**: Form to resolve a conflict of a delivery item. Shows the resolve conflict widget form for all the translations of the source entity.
 - **delivery_item.status**: Returns a JSON response with the status of a delivery item.
 - **delivery.delivery_status_controller**: Returns a JSON resposne with the status of the delivery (basically the results of the getDeliveryDataByWorkspace() call on the delivery.service service).
 - **delivery.update_revisions_index** (Update revision tree index): this seems to point to a non-existent class: \Drupal\delivery\Form\DeliveryIndexForm

The module also has links, tasks and action menu items, they can just be seen in the yml files. It also provides the '\*\*\*CURRENT_WORKSPACE\*\*\*' views query substitution for the current workspace.

# Submodules:
 - **delivery_update**:
   - adds the 'revision_parent' field to all the revisionable entity types.
   - adds the 'parent_workspace' to the workspace entity type.
   - at the same time, it has a script: 'deliver_update_post_update_migrate' to update data from parent_workspace to parent, and from revision_parent__target_id and revision_parent__merge_target_id to revision_parent and revision_merge_parent.
   - it defines a field type, revision_tree, with two columns: target_id and merge_target_id.

 - **workspaces_allowed_languages**: Provides some language fields on workspaces to restrict the languages which can be used when accessing that workspace:
   - adds the primary_language and secondary_languages fields to workspaces.
   - workspaces_allowed_languages_country_switcher_links_alter: a hook that is possibly not used, could not find other references to it.
   - workspaces_allowed_languages_language_switch_links_alter: this ones uses a field called 'allowed_languages' that is probably not defined anywhere anymore (I guess it comes from an older implementation of the module), so this hook should be refactored.
   - workspaces_allowed_languages_process_language_select: Preprocess the language selection field (language_select) to only allow languages assigned to the current workspace.
   - has an event listener: workspaces_allowed_languages.event_listener which listens the kernel.request event and, for users which do not have the 'bypass workspaces language restrictions' permission, it checks if the current languages is allowed om the workspace (matches either the primary language or one of the secondary languages). If not, right now a 404 is returned, but ther is a TODO comment to re-enable the redirect (it did not work reliably before).

 - **workspaces_negotiator_path**: A workspaces negotiator which uses the path information (the path prefix) to determine the current workspace.
   - adds the 'path_prefix' field to workspaces.
   - It implements a workspaces negotiator, workspaces_negotiator_path.prefix, which gets the current workspace by checking if the incoming request has a path prefix which matches one of the workspaces.
   - it has an inbound and outbound path processor, workspaces_negotiator_path.path_processor_domain, to add or check the path prefix of the paths.
   - it also has an event subscriber: workspaces_negotiator_path.event_subscriber which acts on the kernel response event, and for redirect responses it adds the workspace path prefix (?). Question: is this not done already by the outbound processor?
   - it also defines a language negotiation plugin: LanguageNegotiationWorkspaceAndUrl. It extends the LanguageNegotiationUrl class (so basically has the same settings), but it knows how to handle the workspace prefixes together with the language prefixes.

Issues when using the delivery module:
  - it seems that if the workspace does not have any path prefix, cannot be selected, which is probably not a desired thing (the issue is generated by the workspaces_negotiator_path module).
  - the items do not seem to be added to a delivery automatically. So if I create a node in a workspace, and create a delivery from the workspace to another workspace, the delivery does not have any changes and I can't push or pull any items.
