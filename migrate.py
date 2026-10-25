#!/usr/bin/env python3
"""
Resource Monitor - Database Migration Manager

Manages database schema versions and upgrades
"""

import sys
import os
import re
from pathlib import Path
from datetime import datetime
import argparse

import yaml
import pymysql
from pymysql.cursors import DictCursor


class MigrationManager:
    """Handles database schema migrations"""
    
    def __init__(self, config_path='collector/config.yaml'):
        """Initialize migration manager"""
        self.config = self._load_config(config_path)
        self.db = None
        self.migrations_dir = Path(__file__).parent / 'database' / 'migrations'
        
        # Connect to database
        self._connect_db()
        
    def _load_config(self, config_path):
        """Load YAML configuration file"""
        config_file = Path(config_path)
        if not config_file.exists():
            print(f"ERROR: Config file not found: {config_path}")
            print("Please create collector/config.yaml from config.yaml.example")
            sys.exit(1)
            
        with open(config_file, 'r') as f:
            return yaml.safe_load(f)
    
    def _connect_db(self):
        """Connect to MariaDB database"""
        db_config = self.config['database']
        # First connect without selecting database to create it if needed
        try:
            conn = pymysql.connect(
                unix_socket=db_config['unix_socket'],
                port=db_config['port'],
                user=db_config['user'],
                password=db_config['password'],
                charset=db_config['charset'],
                cursorclass=DictCursor,
                autocommit=False
            )
            
            # Create database if it doesn't exist
            cursor = conn.cursor()
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS {db_config['database']}")
            cursor.close()
            conn.close()
            
            # Now connect to the database
            self.db = pymysql.connect(
                unix_socket=db_config['unix_socket'],
                port=db_config['port'],
                user=db_config['user'],
                password=db_config['password'],
                database=db_config['database'],
                charset=db_config['charset'],
                cursorclass=DictCursor,
                autocommit=False
            )
            
            print(f"✓ Connected to database: {db_config['database']}")
            
        except Exception as e:
            print(f"ERROR: Failed to connect to database: {e}")
            sys.exit(1)
    
    def _get_current_version(self):
        """Get current schema version from database"""
        cursor = self.db.cursor()
        
        # Check if schema_version table exists
        cursor.execute("""
            SELECT COUNT(*) as count FROM information_schema.tables 
            WHERE table_schema = %s AND table_name = 'schema_version'
        """, (self.config['database']['database'],))
        
        result = cursor.fetchone()
        
        if result['count'] == 0:
            # No version table = version 0 (fresh install)
            cursor.close()
            return 0
        
        # Get latest version
        cursor.execute("SELECT MAX(version) as version FROM schema_version")
        result = cursor.fetchone()
        cursor.close()
        
        return result['version'] if result['version'] is not None else 0
    
    def _get_available_migrations(self):
        """Get list of available migration files"""
        migrations = []
        
        if not self.migrations_dir.exists():
            print(f"ERROR: Migrations directory not found: {self.migrations_dir}")
            return migrations
        
        # Find all .sql files
        for file in sorted(self.migrations_dir.glob('*.sql')):
            # Extract version number from filename (e.g., "001_initial_schema.sql" -> 1)
            match = re.match(r'^(\d+)_(.+)\.sql$', file.name)
            if match:
                version = int(match.group(1))
                description = match.group(2).replace('_', ' ').title()
                migrations.append({
                    'version': version,
                    'file': file,
                    'description': description
                })
        
        return sorted(migrations, key=lambda x: x['version'])
    
    def _execute_migration(self, migration):
        """Execute a single migration file"""
        print(f"\n{'=' * 60}")
        print(f"Applying migration {migration['version']}: {migration['description']}")
        print(f"{'=' * 60}")
        
        cursor = self.db.cursor()
        
        try:
            # Read migration file
            with open(migration['file'], 'r', encoding='utf-8') as f:
                sql_content = f.read()
            
            # Split by semicolons (handle multi-statement files)
            # This is a simple split - doesn't handle semicolons in strings/comments perfectly
            statements = []
            current_statement = []
            in_delimiter_block = False
            
            for line in sql_content.split('\n'):
                stripped = line.strip()
                
                # Handle DELIMITER changes (for stored procedures)
                if stripped.upper().startswith('DELIMITER'):
                    in_delimiter_block = not in_delimiter_block
                    continue
                
                current_statement.append(line)
                
                # Check for statement end
                if not in_delimiter_block and stripped.endswith(';'):
                    stmt = '\n'.join(current_statement).strip()
                    if stmt and not stmt.startswith('--'):
                        statements.append(stmt)
                    current_statement = []
                elif in_delimiter_block and '$$' in line:
                    # End of procedure
                    stmt = '\n'.join(current_statement).strip()
                    if stmt:
                        statements.append(stmt)
                    current_statement = []
            
            # Execute each statement
            for i, statement in enumerate(statements, 1):
                if statement.strip():
                    try:
                        cursor.execute(statement)
                        print(f"  ✓ Statement {i}/{len(statements)}")
                    except Exception as e:
                        # Skip if already exists (for idempotent migrations)
                        if 'already exists' in str(e).lower():
                            print(f"  ⊙ Statement {i}/{len(statements)} (already exists, skipped)")
                        else:
                            raise
            
            # Record migration in schema_version table
            if migration['version'] > 0:  # Don't record version 0 (version tracking table itself)
                cursor.execute("""
                    INSERT INTO schema_version (version, description)
                    VALUES (%s, %s)
                    ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP
                """, (migration['version'], migration['description']))
            
            self.db.commit()
            print(f"✓ Migration {migration['version']} applied successfully")
            
        except Exception as e:
            self.db.rollback()
            print(f"\n✗ ERROR applying migration {migration['version']}: {e}")
            cursor.close()
            raise
        
        cursor.close()
    
    def status(self):
        """Show current migration status"""
        print("\n" + "=" * 60)
        print("DATABASE MIGRATION STATUS")
        print("=" * 60)
        
        current_version = self._get_current_version()
        available_migrations = self._get_available_migrations()
        
        print(f"\nCurrent schema version: {current_version}")
        print(f"Latest available version: {max([m['version'] for m in available_migrations]) if available_migrations else 0}")
        
        # Get applied migrations
        if current_version > 0:
            cursor = self.db.cursor()
            cursor.execute("SELECT version, description, applied_at FROM schema_version ORDER BY version")
            applied = cursor.fetchall()
            cursor.close()
            
            print(f"\nApplied migrations ({len(applied)}):")
            for migration in applied:
                print(f"  ✓ v{migration['version']:03d}: {migration['description']} ({migration['applied_at']})")
        else:
            print("\nNo migrations applied yet (fresh database)")
        
        # Show pending migrations
        pending = [m for m in available_migrations if m['version'] > current_version]
        if pending:
            print(f"\nPending migrations ({len(pending)}):")
            for migration in pending:
                print(f"  ⊙ v{migration['version']:03d}: {migration['description']}")
        else:
            print("\n✓ Database is up to date!")
        
        print("\n" + "=" * 60)
    
    def list_migrations(self):
        """List all available migrations"""
        print("\n" + "=" * 60)
        print("AVAILABLE MIGRATIONS")
        print("=" * 60)
        
        current_version = self._get_current_version()
        available_migrations = self._get_available_migrations()
        
        if not available_migrations:
            print("\nNo migration files found!")
            return
        
        for migration in available_migrations:
            status = "✓ APPLIED" if migration['version'] <= current_version else "⊙ PENDING"
            print(f"\nv{migration['version']:03d}: {migration['description']}")
            print(f"  Status: {status}")
            print(f"  File: {migration['file'].name}")
    
    def upgrade(self, target_version=None):
        """Upgrade database to target version (or latest)"""
        current_version = self._get_current_version()
        available_migrations = self._get_available_migrations()
        
        # Determine target version
        if target_version is None:
            if available_migrations:
                target_version = max([m['version'] for m in available_migrations])
            else:
                print("No migrations available!")
                return
        
        print("\n" + "=" * 60)
        print("DATABASE MIGRATION - UPGRADE")
        print("=" * 60)
        print(f"\nCurrent version: {current_version}")
        print(f"Target version:  {target_version}")
        
        if current_version >= target_version:
            print("\n✓ Database is already at or above target version!")
            return
        
        # Get migrations to apply
        pending_migrations = [m for m in available_migrations 
                             if current_version < m['version'] <= target_version]
        
        if not pending_migrations:
            print("\nNo migrations to apply!")
            return
        
        print(f"\nWill apply {len(pending_migrations)} migration(s):")
        for m in pending_migrations:
            print(f"  - v{m['version']:03d}: {m['description']}")
        
        # Confirm
        response = input("\nProceed with upgrade? (yes/no): ")
        if response.lower() not in ['yes', 'y']:
            print("Upgrade cancelled.")
            return
        
        # Apply migrations
        print("\n" + "=" * 60)
        print("APPLYING MIGRATIONS")
        print("=" * 60)
        
        for migration in pending_migrations:
            try:
                self._execute_migration(migration)
            except Exception as e:
                print(f"\n✗ Migration failed! Database may be in inconsistent state.")
                print(f"Error: {e}")
                sys.exit(1)
        
        print("\n" + "=" * 60)
        print("✓ UPGRADE COMPLETE!")
        print("=" * 60)
        print(f"\nDatabase upgraded from v{current_version} to v{target_version}")
    
    def init(self):
        """Initialize a fresh database (apply all migrations)"""
        print("\n" + "=" * 60)
        print("INITIALIZING FRESH DATABASE")
        print("=" * 60)
        
        current_version = self._get_current_version()
        
        if current_version > 0:
            print(f"\n✗ Database is already initialized (version {current_version})")
            print("Use 'upgrade' command to apply pending migrations.")
            return
        
        print("\nThis will create all tables and initial data.")
        response = input("Proceed? (yes/no): ")
        if response.lower() not in ['yes', 'y']:
            print("Initialization cancelled.")
            return
        
        # Apply all migrations
        self.upgrade()
    
    def close(self):
        """Close database connection"""
        if self.db:
            self.db.close()


def main():
    """Main entry point"""
    parser = argparse.ArgumentParser(
        description='Resource Monitor Database Migration Manager',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  %(prog)s status              Show current migration status
  %(prog)s list                List all available migrations
  %(prog)s init                Initialize fresh database
  %(prog)s upgrade             Upgrade to latest version
  %(prog)s upgrade --to=5      Upgrade to specific version
        """
    )
    
    subparsers = parser.add_subparsers(dest='command', help='Command to execute')
    
    # Status command
    subparsers.add_parser('status', help='Show current migration status')
    
    # List command
    subparsers.add_parser('list', help='List all available migrations')
    
    # Init command
    subparsers.add_parser('init', help='Initialize fresh database (apply all migrations)')
    
    # Upgrade command
    upgrade_parser = subparsers.add_parser('upgrade', help='Upgrade database to target version')
    upgrade_parser.add_argument('--to', type=int, dest='target_version', 
                               help='Target version (default: latest)')
    
    args = parser.parse_args()
    
    # Show help if no command
    if not args.command:
        parser.print_help()
        sys.exit(0)
    
    # Create migration manager
    try:
        manager = MigrationManager()
        
        # Execute command
        if args.command == 'status':
            manager.status()
        elif args.command == 'list':
            manager.list_migrations()
        elif args.command == 'init':
            manager.init()
        elif args.command == 'upgrade':
            manager.upgrade(args.target_version)
        
        manager.close()
        
    except KeyboardInterrupt:
        print("\n\nOperation cancelled by user.")
        sys.exit(1)
    except Exception as e:
        print(f"\nERROR: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
